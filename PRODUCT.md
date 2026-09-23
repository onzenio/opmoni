# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

O usuário principal é o operador do escritório contábil. No dia a dia ele varre a validade do certificado A1 e da procuração e-CAC e atualiza os cadastros da carteira.

O admin da conta entra para governar o escritório: equipe e conta. Super admin existe para o painel global e para o modo suporte, em que opera dentro de uma conta alheia com auditoria.

## Product Purpose

O opmoni existe para o escritório administrar a carteira fiscal real do tenant atual: identificação, regime tributário, certificado A1 e procuração e-CAC, com alerta de validade.

Sucesso é o operador ver quem está a vencer, vencido ou sem cadastro e agir no mesmo lugar, sem sair da carteira.

## Positioning

A carteira fiscal do escritório num só lugar: CPF/CNPJ, regime, certificado A1 e procuração e-CAC, isolada por conta, com alerta de validade.

## Operating Context

Escritório contábil brasileiro, com várias contas isoladas. A interface e o vocabulário são em português.

O operador começa no painel da carteira e segue para Meus clientes. Lá escolhe Certificados ou Procuração e a situação: todos, a vencer, vencido, válido ou sem cadastro. Pessoa jurídica entra com consulta de CNPJ; pessoa física entra em cadastro manual. Certificado A1 e procuração e-CAC ficam no registro do cliente.

## Capabilities and Constraints

- Conta isolada. CPF/CNPJ é único dentro da conta e pode repetir em contas distintas.
- Papéis: admin, operador e super admin. Modo suporte de super admin em conta alheia fica auditado.
- Certificado digital A1 fica criptografado em disco privado; a senha do arquivo é descartada depois do processamento.
- A procuração e-CAC tem estados derivados da data: ausência, validade, proximidade do vencimento e vencimento.
- Trabalho futuro preserva o isolamento por conta, esses papéis, o modo suporte, o português e o vocabulário fiscal: carteira, certificado A1, procuração e-CAC, regime tributário.

## Brand Commitments

O nome do produto é opmoni. A voz da interface é português direto, com o vocabulário fiscal acima. A interface permanece um dashboard Nuxt UI.

## Evidence on Hand

Há especificação em `openspec/changes/manage-client-portfolio` e `openspec/changes/multi-tenant-accounts`, e o produto em execução no monorepo (API Laravel, dashboard Nuxt).

Não há depoimentos, casos, imprensa nem página de preço. Trabalho futuro não inventa prova social, clientes nomeados nem números de mercado.

## Product Principles

- O trabalho diário do operador é o produto: achar e tratar vencimento de certificado e de procuração.
- Um cliente é um registro fiscal da conta: identificação, regime, A1 e e-CAC.
- Isolamento de conta, papéis e modo suporte não cedem à interface.
- O vocabulário fiscal em português permanece estável.
- O admin governa a conta; o operador opera a carteira.
