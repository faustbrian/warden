[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

Warden is an elegant library for managing roles and permissions in Laravel applications. It provides a simple, expressive API for authorization that integrates seamlessly with Laravel's native authorization system.

This is a fork of [Bouncer](https://github.com/JosephSilber/bouncer) by Joseph Silber, exploring alternative organizational patterns while maintaining compatibility.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/) and Laravel 12+**

## Installation

```bash
composer require cline/warden
```

## Documentation

- **[Getting Started](cookbook/getting-started.md)** - Installation, configuration, and basic concepts
- **[Roles and Abilities](cookbook/roles-and-abilities.md)** - Creating and managing roles and permissions
- **[Model Restrictions](cookbook/model-restrictions.md)** - Scoping abilities to specific models
- **[Ownership](cookbook/ownership.md)** - Handling ownership-based permissions
- **[Removing Permissions](cookbook/removing-permissions.md)** - Disallowing and syncing abilities
- **[Forbidding](cookbook/forbidding.md)** - Explicit denials and blanket restrictions
- **[Checking Permissions](cookbook/checking-permissions.md)** - Various ways to check authorization
- **[Querying](cookbook/querying.md)** - Finding users by roles and abilities
- **[Authorization](cookbook/authorization.md)** - Integration with Laravel's authorization system
- **[Multi-Tenancy](cookbook/multi-tenancy.md)** - Scoping permissions per tenant
- **[Configuration](cookbook/configuration.md)** - Advanced configuration options
- **[Console Commands](cookbook/console-commands.md)** - Artisan commands for maintenance

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [Joseph Silber][link-author]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/warden/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/warden.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/warden.svg

[link-tests]: https://github.com/faustbrian/warden/actions
[link-packagist]: https://packagist.org/packages/cline/warden
[link-downloads]: https://packagist.org/packages/cline/warden
[link-security]: https://github.com/faustbrian/warden/security
[link-maintainer]: https://github.com/faustbrian
[link-author]: https://github.com/JosephSilber/bouncer
[link-contributors]: ../../contributors
