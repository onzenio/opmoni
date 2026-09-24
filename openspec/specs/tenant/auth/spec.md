# Auth Specification

## Purpose

Permite que pessoas criem conta, entrem e saiam do sistema com sessão segura, estabelecendo a identidade usada por todas as demais capacidades de tenancy.

## Requirements

### Requirement: Registro via onboarding cria usuário, conta e assinatura
The system SHALL, on a single registration request with name, email, password, company and team size, create the user, one account named after the company, an `admin` membership linking them, and a subscription on the Basic plan. Registration with credentials SHALL be available only during initial startup, i.e. when the database contains no users or no accounts; otherwise the system SHALL respond 403 and create nothing.

#### Scenario: Primeiro registro da base
- **WHEN** the users table is empty and a registration request arrives
- **THEN** the created user is flagged as super_admin in addition to the account, membership and subscription above

#### Scenario: Registro com base já populada
- **WHEN** users or accounts already exist and a registration request arrives
- **THEN** the system responds 403 and creates nothing (later members are added by an account admin, not by self-registration)

### Requirement: Login e logout por sessão
The system SHALL authenticate login requests with email and password, establishing a server session, and SHALL destroy the session on logout.

#### Scenario: Login válido
- **WHEN** correct credentials are posted to the login endpoint
- **THEN** subsequent requests are authenticated and the current user endpoint returns the user profile

#### Scenario: Credenciais inválidas
- **WHEN** wrong credentials are posted
- **THEN** the system responds 422 without creating a session

### Requirement: Endpoint de usuário atual
The system SHALL expose an authenticated endpoint returning the user, the super_admin flag, the list of accounts the user belongs to, and the current account.

#### Scenario: Sessão ativa
- **WHEN** an authenticated client requests the current user
- **THEN** it receives user data plus accounts and current account

#### Scenario: Sem sessão
- **WHEN** an unauthenticated client requests the current user
- **THEN** the system responds 401
