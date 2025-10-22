# Encrypted Secrets

Store environment-specific secrets for the platform here using SOPS + age.

## Usage

1. Place plaintext templates (e.g. `api.env`) locally.
2. Encrypt with SOPS: `sops --encrypt --in-place infra/secrets/api.env`.
3. Commit only the `.enc` version.

See `docs/secrets-management.md` for the full workflow.
