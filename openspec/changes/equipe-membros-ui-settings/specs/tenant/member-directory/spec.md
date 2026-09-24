## ADDED Requirements

### Requirement: Diretório alimenta a UI Equipe sem misturar gestão no payload
The system SHALL keep the member directory read-only payload (`id`, `name`, `role`, departments; no email) as the data source for the Equipe → Membros list for every tenant member. Invite, role update and remove MUST continue to use the separate members management endpoints authorized by `manageMembers`, not the directory endpoint.

#### Scenario: Directory permanece sem email
- **WHEN** any tenant member requests the member directory
- **THEN** the response includes name, role and departments and MUST NOT include email

#### Scenario: Gestão continua só-admin na API
- **WHEN** an `operador` attempts to invite a member via the members endpoint
- **THEN** the system responds 403 and creates nothing
