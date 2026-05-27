# Market.io deployment on Google Cloud (Cloud Run + Cloud Build)

This guide deploys the `market/` app as three independent Cloud Run services:

- `api` (`market/backend`) as Cloud Run service
- `frontend` (`market/frontend` with Nginx) as Cloud Run service
- `ws-gateway` (`market/ws-gateway`) as Cloud Run service
- Build and deploy via Cloud Build GitHub triggers
- Images stored in Artifact Registry
- Use default Cloud Run URLs (no custom domain required)

## 1) Prerequisites

- GCP project with billing enabled
- `gcloud` CLI authenticated (`gcloud auth login`)
- Project set: `gcloud config set project <PROJECT_ID>`

Enable APIs:

```bash
gcloud services enable \
  run.googleapis.com \
  cloudbuild.googleapis.com \
  artifactregistry.googleapis.com \
  vpcaccess.googleapis.com \
  redis.googleapis.com
```

## 2) Create Artifact Registry (Docker)

```bash
gcloud artifacts repositories create market \
  --repository-format=docker \
  --location=europe-west1 \
  --description="Docker images for Market.io"
```

Artifact registry URL format used by Cloud Build substitutions:

```text
europe-west1-docker.pkg.dev/<PROJECT_ID>/market
```

## 3) Redis for Cloud Run (Memorystore + VPC connector)

Cloud Run cannot use the local `redis` container from `docker-compose`.
Use Memorystore and a Serverless VPC Access connector.

Create connector (example CIDR):

```bash
gcloud compute networks vpc-access connectors create market-connector \
  --region=europe-west1 \
  --network=default \
  --range=10.8.0.0/28
```

Create Redis instance (example):

```bash
gcloud redis instances create market-redis \
  --region=europe-west1 \
  --redis-version=redis_7_0 \
  --size=1 \
  --network=default
```

Get Redis host IP:

```bash
gcloud redis instances describe market-redis --region=europe-west1 --format='value(host)'
```

Use `REDIS_URL=redis://<REDIS_HOST>:6379` and keep `REDIS_PREFIX` (default `market:`).

## 4) Connect GitHub repository in Cloud Build (1st gen)

1. Open **Cloud Build → Triggers**.
2. Click **Connect repository**.
3. Choose **GitHub (Cloud Build GitHub App / 1st gen)**.
4. Select repo `JHKNcz/Webapp_praxe` and authorize access.

## 5) Create 3 triggers (one per service)

Create three triggers in Cloud Build, each using branch `AISlop` (or your deployment branch):

1. **API trigger**
   - Config file: `market/cloudbuild-api.yaml`
2. **Frontend trigger**
   - Config file: `market/cloudbuild-frontend.yaml`
3. **WS Gateway trigger**
   - Config file: `market/cloudbuild-ws-gateway.yaml`

### Required substitutions

Set these substitutions in each trigger:

- `_ARTIFACT_REGISTRY_URL` (for example: `europe-west1-docker.pkg.dev/<PROJECT_ID>/market`)
- `_REGION` (for example: `europe-west1`)
- `_SERVICE_NAME` (service name per trigger)

API trigger:

- `_IMAGE_NAME=api`
- `_REDIS_URL=redis://<REDIS_HOST>:6379`
- `_REDIS_PREFIX=market:`
- `_INITIAL_CASH=10000`
- `_VPC_CONNECTOR=market-connector` (optional but required for private Memorystore)
- `_VPC_EGRESS=private-ranges-only`

WS trigger:

- `_IMAGE_NAME=ws-gateway`
- `_REDIS_URL=redis://<REDIS_HOST>:6379`
- `_REDIS_PREFIX=market:`
- `_VPC_CONNECTOR=market-connector` (optional but required for private Memorystore)
- `_VPC_EGRESS=private-ranges-only`

Frontend trigger:

- `_IMAGE_NAME=frontend`
- `_BACKEND_URL=https://<api-service-url>`
- `_WS_GATEWAY_URL=https://<ws-service-url>`

> `frontend` proxies `/api/*` to `_BACKEND_URL` and `/ws` to `_WS_GATEWAY_URL`.

## 6) Deployment flow

Each trigger does:

1. `docker build`
2. `docker push` to Artifact Registry
3. `gcloud run deploy ... --allow-unauthenticated`

Images are tagged by `${SHORT_SHA}`.

After first successful runs, use Cloud Run default URLs:

- Frontend URL from `market-frontend`
- API URL from `market-api`
- WS URL from `market-ws-gateway`

No custom domain is required.

## 7) Cloud Run runtime notes

- All services are Cloud Run compatible with dynamic `PORT` (default `8080`).
- API starts with: `php -S 0.0.0.0:${PORT} -t public`
- WS gateway reads `PORT` first (fallback `WS_PORT`)
- Frontend Nginx listens on `${PORT}` and proxies by env vars.
