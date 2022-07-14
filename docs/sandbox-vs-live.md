## Sandbox vs Live

By default, plugin operates in the sandbox mode. I means all the transactions would be used with the sandbox URLs and code.
To change it, you need to configure the plugin properly:

```yaml
sylius_pay_pal:
    sandbox: false
```

You can, for example, configure it only for the production environment (e.g. in `config/packages/prod/sylius_pay_pal_plugin.yaml`).

---

Prev: [Installation](installation.md)  
Next: [Onboarding](onboarding.md)
