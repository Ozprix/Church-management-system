# Secrets Management Guide

This document outlines how to manage sensitive configuration for the Church Management SaaS platform across local development, CI, and production environments.

## Guiding Principles
- **Single source of truth:** Store secrets in a central encrypted repository rather than scattering `.env` files.
- **Least privilege:** Only provision the minimum secrets required for each environment and service.
- **Auditability:** Track who changed secrets and when by keeping encrypted blobs in Git and rotating regularly.
- **Separation of concerns:** Application code never hardcodes credentials; everything is injected via environment variables.

## Recommended Tooling

| Use Case | Recommended Tool | Notes |
| --- | --- | --- |
| Local & shared secrets | [Mozilla SOPS](https://github.com/mozilla/sops) with age keys | Encrypt `.env` templates stored under `infra/secrets/`. |
| CI pipelines | GitHub Actions encrypted secrets | Inject database credentials, app keys, etc. during workflows. |
| Production | AWS Secrets Manager or Azure Key Vault | Reference secrets from Terraform (`infra/terraform`) and mount via ECS/Kubernetes providers. |

## Repository Layout

```
infra/
  secrets/
    README.md          # How to contribute secrets
    api.env.enc        # Encrypted Laravel `.env`
    web.env.enc        # Encrypted Next.js `.env`
```

- Encrypted files use the `.enc` extension.
- SOPS configuration (see below) enforces age-based encryption and tracks key IDs.

## Setting Up SOPS

1. Install SOPS and age:
   ```bash
   brew install sops age          # macOS
   sudo apt-get install sops      # Debian/Ubuntu
   ```
2. Generate an age key (one-time):
   ```bash
   age-keygen -o ~/.config/sops/age/keys.txt
   ```
3. Share the **public** portion (`age1…`) with the DevOps team to add to `.sops.yaml`.

### `.sops.yaml`

Create the configuration file at the repo root:

```yaml
creation_rules:
  - path_regex: infra/secrets/.*\.enc$
    age: ["age1examplekey1...", "age1examplekey2..."]
    encrypted_regex: '^(data|stringData)$'
```

### Encrypting Files

```bash
cp apps/api/.env.example infra/secrets/api.env
sops --encrypt --in-place infra/secrets/api.env
# Produces api.env.enc once `.sops.yaml` is present
```

Decrypt when needed:

```bash
sops --decrypt infra/secrets/api.env.enc > apps/api/.env
```

## CI/CD Integration

1. Store age private key as a GitHub Actions secret (`SOPS_AGE_KEY`).
2. Inject the key and decrypt during workflows:

```yaml
- name: Decrypt secrets
  env:
    SOPS_AGE_KEY: ${{ secrets.SOPS_AGE_KEY }}
  run: |
    sops --decrypt infra/secrets/api.env.enc > apps/api/.env
```

3. Reference decrypted environment variables in subsequent steps (tests, container builds, etc.).

## Production Deployment

- Use Terraform modules under `infra/terraform` to create secrets in AWS Secrets Manager (or provider of choice).
- Supply the decrypted values via CI, then allow ECS/Kubernetes to mount secrets as environment variables or files.
- Rotate credentials periodically and re-run `rbac:sync`/migrations as part of deployment pipelines when permission scopes change.

## Operational Checklist

- [ ] SOPS + age installed locally.
- [ ] `.sops.yaml` contains current team keys.
- [ ] Encrypted secrets stored under `infra/secrets/`.
- [ ] GitHub Actions configured with `SOPS_AGE_KEY`.
- [ ] Terraform modules updated to source secrets from the appropriate manager.
- [ ] Documented rotation procedures (`docs/devops.md`) kept in sync with the latest architecture decisions (ADRs).

For questions or onboarding new maintainers, update `docs/devops.md` with the full workflow and link back to this guide.
