<?php

namespace GlpiPlugin\Entrasso;

use Auth;
use Dropdown;
use Glpi\Event;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Profile_User;
use TheNetworg\OAuth2\Client\Provider\Azure;
use TheNetworg\OAuth2\Client\Provider\AzureResourceOwner;
use User;
use UserEmail;

/**
 * The security-critical core of the plugin: decides what local GLPI
 * user (if any) a successfully-validated Microsoft identity maps to.
 * This is the only authorization boundary between "Microsoft says this
 * person is who they claim to be" and "this person gets a live GLPI
 * session" - every branch is logged via Event::log() for audit.
 */
class UserProvisioner
{
    public const REJECT_TENANT_MISMATCH = 'tenant_mismatch';
    public const REJECT_NOT_FOUND       = 'not_found';

    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? Config::get();
    }

    /**
     * @return array{user:?User,reason:?string}
     */
    public function resolveUser(AzureResourceOwner $owner): array
    {
        $tenant_id = (string) $owner->getTenantId();
        $oid       = (string) $owner->getId();
        $upn       = (string) ($owner->getUpn() ?: $owner->getPreferredUsername());
        $email     = (string) $owner->getEmail();

        if ($tenant_id === '' || $oid === '' || $upn === '') {
            $this->logRejection(self::REJECT_NOT_FOUND, 'missing tenant/oid/upn claim');
            return ['user' => null, 'reason' => self::REJECT_NOT_FOUND];
        }

        if ($tenant_id !== $this->config['tenant_id']) {
            $this->logRejection(self::REJECT_TENANT_MISMATCH, "tenant={$tenant_id} upn={$upn}");
            return ['user' => null, 'reason' => self::REJECT_TENANT_MISMATCH];
        }

        // 1. Already linked - the stable, primary lookup path for every
        // login after the first one.
        $account = UserAccount::getForEntraIdentity($tenant_id, $oid);
        if ($account !== null) {
            $user = new User();
            if ($user->getFromDB((int) $account->fields['users_id'])) {
                $account->update([
                    'id'          => $account->getID(),
                    'entra_upn'   => $upn,
                    'entra_email' => $email,
                    'last_login'  => date('Y-m-d H:i:s'),
                ]);
                return ['user' => $user, 'reason' => null];
            }
            // Mapping row points at a since-deleted GLPI user: fall
            // through and treat this as a first-time login.
        }

        // 2. Try to link an existing local/LDAP account by e-mail,
        // instead of creating a duplicate person.
        if (!empty($this->config['match_existing_by_email']) && $email !== '') {
            $existing = new User();
            if (
                $existing->getFromDBbyEmail($email)
                && UserAccount::getForUser((int) $existing->getID()) === null
            ) {
                $this->linkAccount((int) $existing->getID(), $tenant_id, $oid, $upn, $email);
                Event::log(
                    (int) $existing->getID(),
                    'system',
                    3,
                    'login',
                    sprintf('Entrasso: vinculado usuário existente %s à identidade Microsoft %s (oid=%s)', $existing->fields['name'], $upn, $oid)
                );
                return ['user' => $existing, 'reason' => null];
            }
        }

        // 3. Auto-create, if enabled.
        if (!empty($this->config['auto_create'])) {
            $user = $this->createUser($upn, $email, $owner->getFirstName() ?: '', $owner->getLastName() ?: '');
            if ($user !== null) {
                $this->linkAccount((int) $user->getID(), $tenant_id, $oid, $upn, $email);
                Event::log(
                    (int) $user->getID(),
                    'system',
                    3,
                    'login',
                    sprintf('Entrasso: usuário criado automaticamente para %s (oid=%s)', $upn, $oid)
                );
                return ['user' => $user, 'reason' => null];
            }
        }

        $this->logRejection(self::REJECT_NOT_FOUND, "upn={$upn} oid={$oid}");
        return ['user' => null, 'reason' => self::REJECT_NOT_FOUND];
    }

    private function createUser(string $upn, string $email, string $firstname, string $lastname): ?User
    {
        $entities_id = (int) $this->config['entities_id_default'];
        $profiles_id = (int) $this->config['profiles_id_default'];

        $user = new User();
        $input = [
            'name'      => $upn,
            'realname'  => $lastname,
            'firstname' => $firstname,
            'authtype'  => Auth::EXTERNAL,
            'auths_id'  => 0,
            '_extauth'  => 1,
            'is_active' => 1,
            'entities_id' => $entities_id,
        ];
        if ($email !== '') {
            $input['_useremails'] = [0 => $email];
        }

        $id = $user->add($input);
        if (!$id) {
            return null;
        }

        // User::post_addItem() may already have auto-assigned a system
        // default profile (Profile::getDefault()) - that logic only
        // honors an explicit _profiles_id when a session/rights context
        // exists, which isn't the case here (anonymous OAuth callback).
        // Clear whatever it did and assign exactly the profile configured
        // for this plugin, deterministically.
        if ($profiles_id > 0) {
            (new Profile_User())->deleteByCriteria(['users_id' => $id]);
            (new Profile_User())->add([
                'users_id'            => $id,
                'profiles_id'         => $profiles_id,
                'entities_id'         => $entities_id,
                'is_recursive'        => 0,
                'is_dynamic'          => 0,
                'is_default_profile'  => 1,
            ]);
        }

        return $user;
    }

    /**
     * Fills in profile fields (name, phone, job title, office, photo)
     * from Microsoft Graph's `/me` - richer and more current than what
     * fits in the id_token's claims. Runs on every login when enabled,
     * so an admin's manual edits in GLPI get overwritten by whatever is
     * currently in Entra on the person's next sign-in - that trade-off
     * (freshness vs. respecting local edits) is exactly what the
     * `sync_profile_on_login` config toggle is for.
     */
    public function syncProfileFromGraph(User $user, Azure $provider, AccessTokenInterface $token): void
    {
        if (empty($this->config['sync_profile_on_login'])) {
            return;
        }

        $graph = new GraphClient($provider, $token);
        $entities_id = (int) ($user->fields['entities_id'] ?? $this->config['entities_id_default']);

        $profile = $graph->getProfile();
        if ($profile !== null) {
            $this->applyProfileFields($user, $profile, $entities_id);
        }

        $photo = $graph->getPhoto();
        if ($photo !== null) {
            $this->applyPhoto($user, $photo);
        }
    }

    private function applyProfileFields(User $user, array $profile, int $entities_id): void
    {
        $update = ['id' => $user->getID()];
        $changed = false;

        foreach (['firstname' => 'givenName', 'realname' => 'surname'] as $glpi_field => $graph_field) {
            if (!empty($profile[$graph_field])) {
                $update[$glpi_field] = $profile[$graph_field];
                $changed = true;
            }
        }

        if (!empty($profile['mobilePhone'])) {
            $update['mobile'] = $profile['mobilePhone'];
            $changed = true;
        }
        if (!empty($profile['businessPhones'][0])) {
            $update['phone'] = $profile['businessPhones'][0];
            $changed = true;
        }

        // _useremails => [0 => $email] always means "insert a new email
        // row" to User::updateUserEmails() (see src/User.php:1864-1900) -
        // resubmitting an email the user already has crashes on
        // glpi_useremails' unique constraint instead of being a no-op,
        // which was silently aborting the *entire* update() call (name,
        // phone, photo included) every single login. Only include it
        // when it's genuinely a new value for this user.
        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? null;
        if (!empty($email) && !UserEmail::isEmailForUser($user->getID(), $email)) {
            $update['_useremails'] = [0 => $email];
            $changed = true;
        }

        if (!empty($profile['jobTitle'])) {
            $usertitles_id = Dropdown::importExternal('UserTitle', $profile['jobTitle'], $entities_id);
            if ($usertitles_id) {
                $update['usertitles_id'] = $usertitles_id;
                $changed = true;
            }
        }

        if (!empty($profile['officeLocation'])) {
            $locations_id = Dropdown::importExternal('Location', $profile['officeLocation'], $entities_id);
            if ($locations_id) {
                $update['locations_id'] = $locations_id;
                $changed = true;
            }
        }

        if ($changed) {
            $user->update($update);
        }
    }

    /**
     * User::prepareInputForUpdate() (src/User.php:1082-1163) *always*
     * strips a raw `picture` value passed to update() - it's an XSS
     * defense, since picture filenames must be auto-generated by GLPI's
     * own upload flow, never an arbitrary caller-supplied string. The
     * only supported way in is `_picture` pointing at a file already
     * sitting in GLPI_TMP_DIR: core itself then validates it's really an
     * image, moves it into GLPI_PICTURE_DIR with the right subdir/name,
     * builds the thumbnail, and only then sets `picture` internally.
     * Writing straight into GLPI_PICTURE_DIR (the previous approach)
     * left real files on disk that the `picture` column never pointed
     * to - always overwritten back to its old value on the very next
     * save.
     */
    private function applyPhoto(User $user, string $binary): void
    {
        $current = (string) ($user->fields['picture'] ?? '');
        if ($current !== '' && is_file(GLPI_PICTURE_DIR . '/' . $current) && sha1_file(GLPI_PICTURE_DIR . '/' . $current) === sha1($binary)) {
            return; // unchanged since last sync
        }

        $tmp_filename = uniqid('entrasso_photo_') . '.jpg';
        file_put_contents(GLPI_TMP_DIR . '/' . $tmp_filename, $binary);

        $user->update([
            'id'       => $user->getID(),
            '_picture' => [$tmp_filename],
        ]);
    }

    private function linkAccount(int $users_id, string $tenant_id, string $oid, string $upn, string $email): void
    {
        (new UserAccount())->add([
            'users_id'        => $users_id,
            'entra_tenant_id' => $tenant_id,
            'entra_oid'       => $oid,
            'entra_upn'       => $upn,
            'entra_email'     => $email,
            'last_login'      => date('Y-m-d H:i:s'),
        ]);
    }

    private function logRejection(string $reason, string $detail): void
    {
        Event::log(0, 'system', 3, 'login', "Entrasso: login rejeitado ({$reason}) - {$detail}");
    }
}
