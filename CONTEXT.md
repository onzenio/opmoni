# opmoni

Vocabulário da plataforma multi-tenant opmoni, que organiza escritórios em accounts isoladas sob um painel global.

## Language

### Núcleo

**Account**:
A unidade operacional de um escritório: carteira, monitoramentos, documentos, processos e membros próprios.
_Avoid_: Conta, escritório

**Usuário**:
A identidade global de login (email e senha), que pode pertencer a um ou mais accounts.
_Avoid_: Membro

**Membro**:
O vínculo entre um usuário e um account, com um nível (admin, operador ou user).
_Avoid_: Usuário da conta, participante

**Super Admin**:
O usuário com acesso ao painel global e a todos os accounts para suporte.
_Avoid_:

**Admin**:
O nível máximo dentro de um account: gere tudo na conta, incluindo membros e assinatura.
_Avoid_:

**Operador**:
O nível que opera os recursos do account (criar, ler, atualizar, excluir), sem gerenciar membros.
_Avoid_:

**User**:
O nível que lê os recursos do account e executa apenas as próprias ações.
_Avoid_:

### Comercial

**Plano**:
O item do catálogo com limites (Básico, Profissional, Empresarial).
_Avoid_:

**Assinatura**:
O vínculo entre um account e um plano, com status (ativa, inadimplente, cancelada).
_Avoid_:

### Plataforma

**Acesso de suporte**:
A entrada do super_admin em um account alheio com poder de admin, mantendo a própria identidade e registrando as ações.
_Avoid_: Impersonação

**Painel Global**:
O ambiente separado do operacional onde o super_admin gere accounts, planos, assinaturas, usuários e suporte.
_Avoid_: Painel administrativo
