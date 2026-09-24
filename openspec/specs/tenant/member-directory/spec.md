# Member Directory Specification

## Purpose

Permite que qualquer membro do Account veja quem pode executar tarefas (nome, papel e departamentos), sem expor email e sem abrir a gestão de membros.

## Requirements

### Requirement: Diretório legível por qualquer membro do tenant
The system SHALL expose a read-only member directory for the current Account listing every member ordered by name with `id`, `name`, `role` and its departments (`id`, `name`, `color`); the payload MUST NOT contain email or any credential.

#### Scenario: Operador lista a equipe
- **WHEN** an `operador` requests the member directory
- **THEN** the system returns all members of the current Account ordered by name with roles and departments and without email

#### Scenario: User lista a equipe
- **WHEN** a `user` member requests the member directory
- **THEN** the system returns the same directory payload (200), since reading is allowed for every tenant member

#### Scenario: Isolamento entre escritórios
- **WHEN** a member of Account A requests the directory
- **THEN** members of Account B are never returned

#### Scenario: Gestão de membros continua só-admin
- **WHEN** an `operador` attempts to invite a member via the members endpoint
- **THEN** the system responds 403 and creates nothing
