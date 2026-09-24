## ADDED Requirements

### Requirement: Reagendamento do prazo via API

The system SHALL accept `due_on` (nullable date `YYYY-MM-DD`) in `PATCH /tasks/{id}`; `admin` and `operador` members of the task's Account SHALL be able to reschedule or clear the due date; rescheduling SHALL NOT alter status, cascade position, completion timestamp or dismissal reason and SHALL NOT trigger cascade validation (moving the date is not an advancement); `user` members SHALL receive 403; malformed dates SHALL receive 422; support-mode writes SHALL be audited like other task writes; the calendar feed SHALL reflect the new date on subsequent requests.

#### Scenario: Reagendar com sucesso

- **WHEN** an `operador` patches a task with a valid `due_on`
- **THEN** the new due date is persisted and returned, with status and timestamps unchanged

#### Scenario: Limpar o prazo

- **WHEN** an authorized member patches a task with `due_on` null
- **THEN** the task has no due date and drops out of the calendar feed

#### Scenario: User tenta reagendar

- **WHEN** a `user` member patches a task's `due_on`
- **THEN** the system responds 403 and the task is unchanged

#### Scenario: Data malformada recusada

- **WHEN** an authorized member patches a task with a non-date `due_on`
- **THEN** the system responds 422 and the task is unchanged

#### Scenario: Cascata não bloqueia reagendamento

- **WHEN** step 2 of a cascade process is rescheduled while step 1 is still A fazer
- **THEN** the move succeeds because only status advancement is cascade-gated

#### Scenario: Reagendamento em suporte auditado

- **WHEN** a super_admin in support mode reschedules a task
- **THEN** the write is recorded in the support log with resource, verb and identifiers
