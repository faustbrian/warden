# Checking Permissions

Generally speaking, you should not have a need to check roles directly. It is better to allow a role certain abilities, then check for those abilities instead. If what you need is very general, you can create very broad abilities. For example, an `access-dashboard` ability is always better than checking for `admin` or `editor` roles directly.

For the rare occasion that you do want to check a role, that functionality is available.

## Checking Roles

Warden can check if a user has a specific role:

```php
Warden::is($user)->a('moderator');
```

If the role you're checking starts with a vowel, you might want to use the `an` alias method:

```php
Warden::is($user)->an('admin');
```

## Checking for Absence of Roles

You can check if a user *doesn't* have a specific role:

```php
Warden::is($user)->notA('moderator');

Warden::is($user)->notAn('admin');
```

## Checking Multiple Roles

Check if a user has one of many roles:

```php
Warden::is($user)->a('moderator', 'editor');
```

Check if the user has all of the given roles:

```php
Warden::is($user)->all('editor', 'moderator');
```

Check if a user has none of the given roles:

```php
Warden::is($user)->notAn('editor', 'moderator');
```

## Checking Roles on the User Model

These checks can also be done directly on the user:

```php
$user->isAn('admin');
$user->isA('subscriber');

$user->isNotAn('admin');
$user->isNotA('subscriber');

$user->isAll('editor', 'moderator');
```

## Getting All Roles

Get all roles for a user:

```php
$roles = $user->getRoles();
```

## Getting All Abilities

Get all abilities for a user:

```php
$abilities = $user->getAbilities();
```

This will return a collection of the user's allowed abilities, including any abilities granted to the user through their roles.

You can also get a list of abilities that have been explicitly forbidden:

```php
$forbiddenAbilities = $user->getForbiddenAbilities();
```

## Examples

Check if user is an admin:

```php
if (Warden::is($user)->an('admin')) {
    // User is an admin
}
```

Check if user is not a subscriber:

```php
if ($user->isNotA('subscriber')) {
    // User is not a subscriber
}
```

Check if user has multiple roles:

```php
if ($user->isAll('editor', 'moderator')) {
    // User has both editor and moderator roles
}
```

Get all of a user's abilities:

```php
$abilities = $user->getAbilities();

foreach ($abilities as $ability) {
    echo $ability->name;
}
```
