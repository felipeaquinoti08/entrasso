# Entrasso

Plugin para **GLPI 11** que adiciona login SSO com **Microsoft Entra ID** (Azure AD) — de graça, sem depender de plugins pagos, reaproveitando bibliotecas que já vêm dentro do próprio GLPI.

> Requer GLPI `>= 11.0.0`. Licença GPL-3.0-or-later.

---

## O que o plugin resolve

Soluções de SSO com Microsoft Entra ID para GLPI, no mercado de plugins, costumam ser pagas. O Entrasso entrega a mesma coisa reaproveitando a biblioteca `thenetworg/oauth2-azure` (provider Azure para o `league/oauth2-client`), que já está no `vendor/` do próprio GLPI — hoje usada só para SMTP de saída via Microsoft Graph, nunca para login. Nenhuma dependência nova.

Ao logar com a conta Microsoft, se não existir um usuário correspondente no GLPI, um é criado automaticamente — mantendo histórico real (ações atribuídas a uma pessoa de verdade).

---

## Principais características

### 🔑 Login SSO real
- Botão "Entrar com Microsoft" na tela de login (logo abaixo do botão "Entrar"), só aparece quando o plugin está configurado e ativo.
- Fluxo OAuth2/OIDC completo contra o Microsoft identity platform v2.0 (endpoint por tenant, nunca `common`), com validação de `state` (CSRF), verificação de assinatura do id_token e checagem do tenant no servidor.

### 👤 Provisionamento automático de usuário
- Reconhece a pessoa pelo **Object ID (oid)** do Entra — estável mesmo que o e-mail ou nome mude depois, ao contrário de usar e-mail como chave.
- Primeiro login: tenta vincular a um usuário **já existente** no GLPI (local ou LDAP) pelo e-mail, em vez de duplicar. Se não achar, cria um novo automaticamente, com o perfil e entidade configurados.
- Aba "Conta Microsoft" na tela de cada usuário do GLPI, mostrando o vínculo — a trilha de auditoria.
- Toda decisão (vinculou/criou/rejeitou) fica registrada no log nativo do GLPI.

### 🔄 Sincronização de perfil via Microsoft Graph
- A cada login, preenche automaticamente nome, e-mail, telefone/celular, foto de perfil, cargo e localidade — usando a permissão `User.Read` do Graph (já habilitada por padrão em qualquer app registrado).
- Cargo e Localidade são campos de seleção no GLPI: se o valor vindo da Microsoft ainda não existir, é criado automaticamente (mesmo padrão que a sincronização LDAP já usa).
- Sincronização configurável (liga/desliga).

### 🧙 Assistente de configuração do aplicativo no Azure
- Tela de configuração com passo a passo guiado, nome e Redirect URI prontos para copiar, e link direto para o Portal do Azure — sem precisar caçar a tela certa.
- Segredo do cliente (`client_secret`) armazenado **criptografado em repouso**, via mecanismo nativo do GLPI (`SECURED_CONFIGS`).
- Botão de emergência (`is_active`) para desligar o SSO instantaneamente, sem precisar desinstalar o plugin.

---

## Estrutura do plugin

```
entrasso/
├── front/          Controllers (redirect pro Microsoft, callback OAuth, configuração)
├── install/        Instalação e migrações
├── src/            Classes principais (OAuthClient, UserProvisioner, GraphClient, UserAccount, ...)
└── setup.php       Registro do plugin no GLPI
```

## Instalação

1. Coloque a pasta em `plugins/entrasso/` dentro da instalação do GLPI.
2. Em **Configurar > Plugins**, instale e ative o Entrasso.
3. Clique no ícone de engrenagem do Entrasso para configurar: siga o assistente para registrar o aplicativo no Portal do Azure (como Global Admin) e cole o Client ID, Client Secret e Tenant ID.
