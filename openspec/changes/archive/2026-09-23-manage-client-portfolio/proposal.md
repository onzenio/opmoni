## Why

A página `/customers` ainda usa dados fictícios e o cadastro de clientes no backend contém apenas um nome, o que impede o escritório de administrar sua carteira fiscal real. A plataforma precisa conectar a interface ao tenant atual e reunir identificação, situação, regime tributário, certificado A1 e procuração e-CAC com alertas de validade.

## What Changes

- Transformar Cliente no registro de uma pessoa jurídica ou física pertencente à carteira de um Account, com CPF/CNPJ único dentro desse Account e permitido em Accounts distintos
- Substituir a tabela fictícia de `/customers` por listagem paginada, pesquisável e filtrável, com CRUD completo e ações de situação
- Consultar CNPJs pelo backend na API pública CNPJ.ws antes do cadastro, normalizando somente os dados cadastrais necessários e respeitando o limite externo
- Definir o regime tributário de forma híbrida: MEI/Simples a partir da consulta e Lucro Presumido, Lucro Real ou Outro por seleção do usuário
- Permitir cadastro manual de pessoa física, sem consulta externa e com regime Não aplicável
- Armazenar certificado digital A1 criptografado em disco privado, validando o PFX/P12 e descartando sua senha após o processamento
- Controlar a validade da procuração e-CAC e apresentar estados de ausência, validade, proximidade do vencimento e vencimento
- Preservar isolamento tenant, permissões por nível, limites do plano e auditoria das operações realizadas em modo de suporte
- Remover o endpoint mockado e os componentes fictícios de clientes do frontend

## Capabilities

### New Capabilities
- `tenant/client-portfolio`: cadastro, listagem, consulta, alteração, situação e exclusão lógica de clientes da carteira do Account
- `tenant/cnpj-lookup`: consulta controlada à CNPJ.ws, cache, normalização dos dados e definição assistida do regime tributário
- `tenant/client-fiscal-access`: armazenamento seguro do certificado A1 e controle da procuração e-CAC com estados derivados de validade

### Modified Capabilities
- (nenhuma — os requisitos de isolamento, níveis, suporte e limites já definidos por `multi-tenant-accounts` continuam válidos sem alteração)

## Impact

- Backend Laravel: modelo e schema de clientes, novos modelos de certificado e procuração, integração HTTP externa, validação de CPF/CNPJ, armazenamento privado criptografado, API tenant, resources, filtros e testes
- Frontend Nuxt: reescrita de `/customers`, tipos e composable de API, tabela Nuxt UI, slideover de cadastro/edição e modais para credenciais fiscais
- API externa: `https://publica.cnpj.ws/cnpj/{cnpj}`, limitada a três consultas por minuto; a integração exigirá cache e tratamento de indisponibilidade e rate limit
- Compatibilidade: os consumidores atuais de `/api/clients` deverão passar a enviar os novos campos obrigatórios de identificação; os testes internos baseados apenas em `name` serão atualizados
- Dependência funcional: mantém como pré-requisito o tenancy, as policies, os limites de assinatura e o acesso de suporte planejados em `multi-tenant-accounts`
