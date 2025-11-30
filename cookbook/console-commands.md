# Console Commands

## warden:clean

The `warden:clean` command deletes unused abilities. Running this command will delete 2 types of unused abilities.

### Unassigned Abilities

Abilities that are not assigned to anyone. For example:

```php
Warden::allow($user)->to('view', Plan::class);

Warden::disallow($user)->to('view', Plan::class);
```

At this point, the "view plans" ability is not assigned to anyone, so it'll get deleted.

**Note:** depending on the context of your app, you may not want to delete these. If you let your users manage abilities in your app's UI, you probably *don't* want to delete unassigned abilities.

### Orphaned Abilities

Model abilities whose models have been deleted:

```php
Warden::allow($user)->to('delete', $plan);

$plan->delete();
```

Since the plan no longer exists, the ability is no longer of any use, so it'll get deleted.

## Usage

Run the command to delete both types of unused abilities:

```bash
php artisan warden:clean
```

## Flags

If you only want to delete one type of unused ability, run it with one of the following flags:

```bash
php artisan warden:clean --unassigned
```

```bash
php artisan warden:clean --orphaned
```

## Scheduling

To automatically run this command periodically, add it to your console kernel's schedule:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('warden:clean')->weekly();
}
```

Or run it daily:

```php
$schedule->command('warden:clean')->daily();
```

Or run it monthly:

```php
$schedule->command('warden:clean')->monthly();
```

## Examples

Clean all unused abilities:

```bash
php artisan warden:clean
```

Clean only unassigned abilities:

```bash
php artisan warden:clean --unassigned
```

Clean only orphaned abilities:

```bash
php artisan warden:clean --orphaned
```

Schedule weekly cleanup in `app/Console/Kernel.php`:

```php
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule)
{
    $schedule->command('warden:clean')->weekly();
}
```
