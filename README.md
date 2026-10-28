# PAM Native Location

Device location for PAM Native apps: the current position, the last known position, throttled watches, the location services switch (with the Play Services "turn on location" dialog on Android) and typed failures.

Moved out of the PAM Native core in 1.46: the core keeps only the base every
app needs, and this feature ships as a plugin. The native module name and its
wire payloads did not change.

## Install

```bash
composer require pushinbr/pam-native-location
```

Requires `pushinbr/pam-native` 1.46 or newer (plugin hooks `WatchChannel` and
`ActivityResultHost`). The plugin is discovered from Composer; no manual
registration is needed.

## Use

```php
use Pam\Native\Location\Location;
use Pam\Native\Location\LocationError;
use Pam\Native\Location\LocationPosition;

Location::current(
    static function (LocationPosition $position): void {
        // $position->latitude, $position->longitude, $position->accuracy
    },
    static function (string $failure): void {
        $error = LocationError::fromFailure($failure); // Permission, Disabled, Unavailable, Timeout
    },
);
```

## Native

- Module: `location` (Android and iOS).
- Permissions: `android.permission.ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION`; iOS `NSLocationWhenInUseUsageDescription`.
- Android dependencies: Play Services Location 21.4.0 (fused provider; `LocationManager` fallback without Play Services).

## Migrating from the core

The 1.46 core keeps the old class names (`Pam\Native\System\...` and the
top-level value types) as deprecated aliases of this plugin's classes; they
are removed in 1.47. Replace the imports with the `Pam\Native\...` names
above.

## Tests

```bash
php tests/run.php            # after composer install
PAM_NATIVE_SRC=../pam-native/packages/native/src php tests/run.php
```
