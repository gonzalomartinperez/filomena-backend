# Security policy

This repository is a portfolio/reference snapshot. It does not contain production credentials or institutional data.

## Safe setup

- Never commit `.env`; start from the appropriate `.env.example.*` template.
- Replace every required credential with a unique value before starting a stack.
- Keep `PROMETHEUS_BEARER_TOKEN`, database credentials, Grafana credentials and the initial administrator credentials outside version control.
- Do not expose database, phpMyAdmin, Prometheus or Grafana ports publicly without an authenticated reverse proxy and network controls.
- Use synthetic data only in local development.

If you find a security issue, do not publish exploit details or sensitive data in a public issue. Use GitHub's private vulnerability reporting channel when it is available for this repository.
