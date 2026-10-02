# Entrasso

**Login único (SSO) com Microsoft Entra ID para o GLPI 11: botão "Entrar com Microsoft", criação e vínculo automático de usuários e sincronização de perfil pelo Microsoft Graph. Gratuito e de código aberto.**

![GLPI](https://img.shields.io/badge/GLPI-11.0.x-2f6fed)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![Licença](https://img.shields.io/badge/licen%C3%A7a-GPL--3.0--or--later-green)

<!--
Capturas de tela: adicione as imagens em docs/img/ e descomente.
![Botão na tela de login](docs/img/login.png)
![Configuração](docs/img/config.png)
-->

---

## Sumário

- [Por que usar](#por-que-usar)
- [Recursos](#recursos)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração no Microsoft 365](#configuração-no-microsoft-365)
- [Como o usuário é encontrado ou criado](#como-o-usuário-é-encontrado-ou-criado)
- [Atualização](#atualização)
- [Desativar ou desinstalar](#desativar-ou-desinstalar)
- [Onde ficam os dados](#onde-ficam-os-dados)
- [Segurança](#segurança)
- [Solução de problemas](#solução-de-problemas)
- [Estrutura](#estrutura)
- [Contribuindo](#contribuindo)
- [Licença](#licença)

---

## Por que usar

- **Um login só.** Os colaboradores entram no GLPI com a mesma conta Microsoft 365 que já usam no dia a dia, sem mais uma senha para lembrar.
- **Nenhuma dependência nova.** O plugin reaproveita a biblioteca `thenetworg/oauth2-azure`, que já vem dentro do próprio GLPI 11 (usada pelo core só para SMTP via Microsoft Graph).
- **Usuários de verdade.** Se a pessoa ainda não existe no GLPI, ela é criada ou vinculada a um cadastro existente automaticamente, e o histórico fica atribuído a ela.
- **Configuração guiada.** A tela de configuração traz o passo a passo do Portal do Azure com os valores prontos para copiar.
- **Gratuito.** Sem licença, sem chave, sem limite de instalações.

---

## Recursos

### Login
- Botão **"Entrar com Microsoft"** na tela de login, no padrão visual da Microsoft (logo de quatro quadrados), com variante escura. Só aparece quando o plugin está ativo e configurado. O texto do botão é editável.
- Fluxo OAuth 2.0 / OpenID Connect no Microsoft identity platform v2.0, sempre no endpoint **do seu tenant** (nunca `common`), com parâmetro `state` contra CSRF e checagem do tenant no servidor.
- **Botão de emergência**: desligue o SSO na hora, sem desinstalar o plugin.

### Usuários
- Identificação pelo **Object ID (oid)** do Entra ID, que continua o mesmo mesmo que o e-mail ou o nome mudem.
- No primeiro login, vincula a um usuário **já existente** (local ou LDAP) pelo e-mail ou **cria um novo**, com a entidade e o perfil padrão escolhidos.
- Aba **"Conta Microsoft"** no cadastro de cada usuário, mostrando o vínculo: UPN, e-mail, oid, tenant, data do vínculo e último login.
- Cada decisão (vinculou, criou, recusou) fica registrada no log de eventos do GLPI.

### Sincronização de perfil (Microsoft Graph)
- A cada login, preenche **nome, sobrenome, telefone, celular, foto, cargo e localidade** com o que está no perfil Microsoft 365 da pessoa, e adiciona o **e-mail** se ele ainda não estiver no cadastro.
- Cargo e Localidade são criados no GLPI se ainda não existirem, como na sincronização LDAP.
- Usa só a permissão `User.Read`, que já vem habilitada em qualquer aplicativo registrado. Pode ser desligada.
- Uma falha do Graph (limite de requisições, instabilidade) nunca impede o login: a pessoa entra normalmente e a falha fica registrada no log.

### Tela de configuração
- Visão geral com a situação do login, do aplicativo no Azure e do HTTPS do GLPI.
- Guia do Azure em passos numerados, com **nome do aplicativo e URI de redirecionamento prontos para copiar** e link direto para o portal.
- `client_secret` guardado **criptografado** pelo próprio GLPI (`SECURED_CONFIGS`).

---

## Requisitos

- **GLPI 11.0.x**
- **PHP 8.2 ou superior**
- GLPI acessível por **HTTPS** (o Azure recusa URIs de redirecionamento HTTP, exceto em `localhost`)
- Uma conta de **Administrador Global** (ou com permissão para registrar aplicativos) no tenant Microsoft 365

---

## Instalação

1. Baixe o plugin para a pasta `plugins` do GLPI. **A pasta precisa se chamar `entrasso`:**

   ```bash
   cd /var/www/glpi/plugins
   git clone https://github.com/felipeaquinoti08/entrasso.git entrasso
   chown -R www-data:www-data entrasso
   ```

   Ou baixe o ZIP em **Code > Download ZIP** no GitHub, extraia e renomeie a pasta para `entrasso`.

2. Em **Configurar > Plugins**, clique em **Instalar** e depois em **Ativar**.

   Pela linha de comando, a partir da pasta do GLPI:

   ```bash
   sudo -u www-data php bin/console plugin:install --username=glpi entrasso
   sudo -u www-data php bin/console plugin:activate entrasso
   ```

   Troque `glpi` pelo login de um administrador. Com Docker, rode os mesmos comandos com `docker exec -u www-data <contêiner-do-glpi>`.

3. Clique na engrenagem do plugin e siga a [configuração no Microsoft 365](#configuração-no-microsoft-365).

---

## Configuração no Microsoft 365

A tela de configuração do plugin mostra este mesmo passo a passo, com os valores do **seu** GLPI prontos para copiar.

1. No [Portal do Azure](https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade), logado como Administrador Global, vá em **Microsoft Entra ID > Registros de aplicativo > Novo registro**.
2. **Nome**: use o sugerido pelo plugin (ex.: `GLPI SSO - suporte.suaempresa.com`).
3. **Tipos de conta com suporte**: *Somente contas neste diretório organizacional* (single-tenant).
4. **URI de redirecionamento**: tipo **Web**, com o endereço mostrado pelo plugin, no formato `https://<seu-glpi>/plugins/entrasso/front/callback.php`.
5. Clique em **Registrar**. Na visão geral do aplicativo, copie o **Application (client) ID** e o **Directory (tenant) ID** para o plugin.
6. Em **Certificados e segredos > Novo segredo do cliente**, copie o **Valor** (não o ID do segredo) para o campo **Client secret**.
7. No plugin, ajuste as opções de usuários, ligue **Mostrar "Entrar com Microsoft" na tela de login** e salve.

> O segredo do cliente expira (o Azure oferece de 6 a 24 meses). Anote a data e gere um novo antes do vencimento: basta colar o valor novo no plugin e salvar.

---

## Como o usuário é encontrado ou criado

A cada login com Microsoft, o plugin segue esta ordem:

1. **Confere o tenant.** Contas de outro tenant são recusadas.
2. **Procura pelo vínculo** (oid + tenant). Se encontrar, entra com esse usuário.
3. **Procura pelo e-mail**, se *Vincular a um usuário já existente pelo e-mail* estiver ligado. Se houver **exatamente um** usuário com esse e-mail, vincula e entra.
4. **Cria um usuário novo**, se *Criar um usuário novo quando não encontrar nenhum* estiver ligado, com a entidade e o perfil padrão.
5. **Recusa o login** se nenhuma das anteriores resolveu. A pessoa vê a mensagem "Sua conta Microsoft não está autorizada a acessar este GLPI".

Tudo é registrado no log de eventos do GLPI (*Administração > Logs*).

---

## Atualização

```bash
cd /var/www/glpi/plugins/entrasso
git pull
chown -R www-data:www-data .
```

Depois, em **Configurar > Plugins**, clique em **Atualizar** no Entrasso e ative de novo, se o GLPI pedir.

### Vindo da versão 1.0.x (com licenciamento)

A partir da **1.1.0** o Entrasso é gratuito. Ao atualizar, o plugin remove sozinho a tarefa agendada de verificação de licença (*CheckIn*) e as configurações de licença. Nenhuma chave é necessária, e as demais configurações (aplicativo, usuários, sincronização) são mantidas.

---

## Desativar ou desinstalar

- **Desligar o botão** na configuração (ou **desativar** o plugin) interrompe o SSO na hora e mantém tudo configurado.
- **Desinstalar** apaga as configurações e os vínculos entre usuários do GLPI e contas Microsoft. Os usuários continuam existindo no GLPI.

---

## Onde ficam os dados

| O quê | Onde |
|---|---|
| Configurações | Tabela `glpi_configs`, contexto `plugin:entrasso` (o `client_secret` fica criptografado) |
| Vínculos usuário ↔ conta Microsoft | Tabela `glpi_plugin_entrasso_useraccounts` |

---

## Segurança

- Endpoint **do tenant configurado**, nunca `common` ou `organizations`, e checagem do tenant da conta no servidor.
- Parâmetro `state` de uso único, com validade de 10 minutos, contra CSRF no retorno da Microsoft.
- `client_secret` criptografado em repouso pelo mecanismo nativo do GLPI.
- A foto do perfil entra pelo fluxo de upload do próprio GLPI, que gera o nome do arquivo e a miniatura.
- Só as páginas de ida e volta do login (`front/redirect.php` e `front/callback.php`) são acessíveis sem sessão. A configuração exige o direito **Configuração > Atualizar**.

---

## Solução de problemas

<details>
<summary><strong>O botão "Entrar com Microsoft" não aparece</strong></summary>

O botão só aparece quando **Mostrar "Entrar com Microsoft" na tela de login** está ligado e o **Client ID** e o **Tenant ID** estão preenchidos. Confira também se o plugin está ativo em **Configurar > Plugins**.
</details>

<details>
<summary><strong>Erro AADSTS50011 (URI de redirecionamento não corresponde)</strong></summary>

A URI cadastrada no Azure precisa ser **idêntica** à mostrada pelo plugin, incluindo `https://`, o domínio e o caminho. Ela é montada a partir de *Configurar > Geral > URL da aplicação* do GLPI: se essa URL estiver errada, corrija lá primeiro.
</details>

<details>
<summary><strong>"Sua conta Microsoft não está autorizada a acessar este GLPI"</strong></summary>

Nenhuma regra resolveu o usuário: a conta é de outro tenant, ou não há vínculo, e a busca por e-mail e a criação automática estão desligadas (ou há mais de um usuário com o mesmo e-mail). Veja o motivo em *Administração > Logs*.
</details>

<details>
<summary><strong>"Não foi possível verificar a autenticidade da resposta da Microsoft"</strong></summary>

A sessão do navegador não voltou junto com a resposta da Microsoft, ou a tentativa passou de 10 minutos. Tente de novo. Se persistir, verifique se o cookie de sessão do GLPI não está com `SameSite=Strict` num proxy na frente do GLPI.
</details>

<details>
<summary><strong>Os dados do perfil não são atualizados</strong></summary>

Confira se **Preencher automaticamente...** está ligado e se o aplicativo mantém a permissão `User.Read` (em *Permissões de API* no Azure). Falhas do Graph não bloqueiam o login, mas ficam registradas no log de erros do PHP do GLPI.
</details>

---

## Estrutura

```
entrasso/
├── setup.php                 # registro do plugin e dos hooks
├── install/                  # instalação, atualização e desinstalação
├── front/
│   ├── config.php            # tela de configuração
│   ├── redirect.php          # envia para o login da Microsoft
│   └── callback.php          # retorno da Microsoft, autentica no GLPI
├── src/
│   ├── Config.php            # configurações (client_secret criptografado)
│   ├── OAuthClient.php       # provider Azure (OpenID Connect)
│   ├── UserProvisioner.php   # vínculo, criação e sincronização do usuário
│   ├── GraphClient.php       # perfil e foto no Microsoft Graph
│   ├── UserAccount.php       # vínculo usuário ↔ conta Microsoft (aba no usuário)
│   ├── LoginButton.php       # botão na tela de login
│   └── Ui.php                # componentes visuais das telas
└── public/css/ui.css         # estilos das telas
```

---

## Contribuindo

Sugestões, relatos de problemas e pull requests são bem-vindos.

- **Problemas**: abra uma [issue](https://github.com/felipeaquinoti08/entrasso/issues) com a versão do GLPI, os passos para reproduzir e a mensagem de erro (sem segredos ou IDs sensíveis).
- **Pull requests**: uma branch por assunto, a partir da `main`, mantendo o estilo do código ao redor.

### Outros plugins gratuitos do mesmo autor

- [GLPI Style](https://github.com/felipeaquinoti08/glpi_style): tela de login premium, logos, cores e visual interno moderno para o GLPI. O botão do Entrasso se integra à tela de login dele.
- [Termodocs](https://github.com/felipeaquinoti08/termodoc): termos de entrega e devolução de equipamentos com assinatura eletrônica, lembretes por e-mail e link seguro para quem não entra no GLPI.
- [Sentinela](https://github.com/felipeaquinoti08/sentinela): auditoria de softwares instalados, com políticas de lista branca e lista negra e alertas por e-mail.

Todos são gratuitos e distribuídos sob a mesma licença (GPL-3.0-or-later).

---

## Licença

Distribuído sob a **GNU General Public License v3.0 ou posterior**. Veja o arquivo [LICENSE](LICENSE).

Desenvolvido por **Felipe Aquino**.
