# Estudo de Arquitetura: Idempotência em Webhooks de Pagamento

[![CI](https://github.com/TarcisioAraujo7/idempotent-webhook-study-case/actions/workflows/ci.yml/badge.svg)](https://github.com/TarcisioAraujo7/idempotent-webhook-study-case/actions/workflows/ci.yml)

Estudo de arquitetura backend em Laravel sobre idempotência aplicada a webhooks de pagamento.

O objetivo é demonstrar, de forma prática, como diferentes estratégias lidam com o mesmo problema: uma requisição de webhook pode chegar mais de uma vez e não deve gerar efeitos duplicados.



## Escopo

Este projeto simula um endpoint de webhook de pagamento que despacha o processamento assíncrono de uma transferência bancária.

O estudo compara quatro abordagens:

- sem proteção contra duplicidade;
- deduplicação temporária com Redis;
- idempotência durável com banco de dados;
- estratégia híbrida usando Redis e banco.

## Problema Estudado

Webhooks podem ser reenviados por timeout, falha de rede, retry automático ou concorrência entre entregas próximas.

Sem uma estratégia de idempotência, a mesma intenção de pagamento pode disparar múltiplos jobs, criar registros duplicados ou repetir operações custosas.

## Stack

- PHP 8.4+
- Laravel 13
- MySQL 8.4
- Redis 7 com `predis/predis`
- Queue do Laravel
- Docker Compose
- Pest/PHPUnit

## Fases Do Estudo

| Fase | Endpoint | Estratégia |
| --- | --- | --- |
| 1 | `POST /api/webhooks/payments/phase-1` | Sem idempotência |
| 2 | `POST /api/webhooks/payments/phase-2` | Redis com TTL |
| 3 | `POST /api/webhooks/payments/phase-3` | Header de idempotência + MySQL |
| 4 | `POST /api/webhooks/payments/phase-4` | Redis lock + MySQL |

## Estratégias

### Fase 1: Sem Idempotência

Recebe o webhook, valida o payload e despacha o job. Se a mesma requisição chegar várias vezes, vários processamentos podem ser criados.

### Fase 2: Redis

Gera uma chave a partir do payload normalizado e usa Redis com `SET NX EX` para bloquear duplicatas dentro de uma janela curta.

É uma boa proteção contra retries próximos, mas não é uma garantia durável.

### Fase 3: Header de idempotência + MySQL

A identidade da operação passa a vir do header `Idempotency-Key` ou `X-Idempotency-Key`.

A aplicação registra essa chave na tabela `payment_webhook_receipts`, com índice único em `idempotency_key` e status do evento recebido. Se a mesma chave já existir, a requisição é tratada como duplicada.

### Fase 4: Híbrida

Combina Redis e MySQL:

- Redis atua como lock rápido contra concorrência imediata;
- MySQL mantém o histórico e a garantia persistente de unicidade.


## Como Rodar

Com Docker:

```bash
docker compose up --build
```

A aplicação ficará disponível em `http://127.0.0.1:8000`. O Compose sobe MySQL e Redis, executa as migrations e inicia o worker da fila junto com o servidor Laravel.

Para rodar os testes dentro do container:

```bash
docker compose exec app php artisan test
```

Para remover os containers e volumes:

```bash
docker compose down -v
```

Sem Docker:

Instale as dependências:

```bash
composer install
```

Prepare o ambiente:

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Suba a aplicação:

```bash
php artisan serve
```

Rode a fila:

```bash
php artisan queue:work
```

## Exemplo De Requisição

```bash
curl -X POST http://127.0.0.1:8000/api/webhooks/payments/phase-4 \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: payment-demo-0001" \
  -d '{
    "payer_name": "João Silva",
    "payer_document": "123.456.789-00",
    "amount_in_cents": 15000,
    "bank_code": "001",
    "branch_number": "1234",
    "account_number": "56789-0"
  }'
```

Ao repetir a mesma requisição com o mesmo `Idempotency-Key`, a aplicação deve responder como duplicada.



## Limitações

- O projeto foca na entrada idempotente do webhook.
- O job assíncrono ainda deveria ser idempotente em um sistema de produção.
- O projeto não implementa autenticação, assinatura de webhook ou observabilidade completa.
- A fase Redis-only não garante idempotência após expiração do TTL.

## Agradecimentos

Obrigado por acompanhar este estudo de arquitetura.

Caso queira trocar uma ideia sobre backend, arquitetura ou desenvolvimento de software, deixo abaixo meu LinkedIn:

LinkedIn: https://www.linkedin.com/in/tarcisioaraujo7/.
