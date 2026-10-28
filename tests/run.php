<?php

declare(strict_types=1);

use Pam\Native\Internal\Runtime;
use Pam\Native\Internal\Wire;
use Pam\Native\Location\Location;
use Pam\Native\Location\LocationPosition;
use Pam\Native\ModuleResultStatus;

require __DIR__.'/harness.php';

$location = null;
$locationRequest = Location::current(
    static function (LocationPosition $position) use (&$location): void {
        $location = $position;
    },
    highAccuracy: false,
    timeoutMs: 4_000,
    maximumAgeMs: 12_000,
);
$locationCall = TestDiagnostics::$moduleCall;
$locationPayload = $locationCall === null
    ? []
    : Wire::decodeMap($locationCall['payload']);
$assert(
    $locationCall !== null
        && $locationCall['requestId'] === $locationRequest
        && $locationCall['module'] === 'location'
        && $locationCall['method'] === 'current'
        && $locationPayload === [
            'highAccuracy' => false,
            'maximumAgeMs' => 12_000,
            'timeoutMs' => 4_000,
        ],
    'Location facade did not emit its typed native module call.',
);
Runtime::dispatchModuleResult(
    $locationRequest,
    ModuleResultStatus::Success->value,
    Wire::map([
        'latitude' => -23.55052,
        'longitude' => -46.633308,
        'accuracy' => 3.5,
        'altitude' => 760.0,
        'speed' => 0.4,
        'bearing' => 90.0,
        'timestamp' => 1_785_000_000_000,
    ]),
);
$assert(
    $location instanceof LocationPosition
        && $location->latitude === -23.55052
        && $location->longitude === -46.633308
        && $location->accuracy === 3.5
        && $location->timestamp === 1_785_000_000_000,
    'Location facade did not decode the native position.',
);

$locationFailure = null;
$failedLocationRequest = Location::current(
    static function (LocationPosition $_): void {
        throw new RuntimeException('A failed location request must not resolve a position.');
    },
    failure: static function (string $message) use (&$locationFailure): void {
        $locationFailure = $message;
    },
);
Runtime::dispatchModuleResult(
    $failedLocationRequest,
    ModuleResultStatus::Failure->value,
    'Location request timed out.',
);
$assert(
    $locationFailure === 'Location request timed out.',
    'Location failures must reach the optional failure callback.',
);

$watched = [];
$watchId = Location::watch(
    static function (LocationPosition $position) use (&$watched): void {
        $watched[] = $position->latitude;
    },
    highAccuracy: true,
    distanceFilterMeters: 20.0,
    intervalMs: 5_000,
);
$watchCall = TestDiagnostics::$moduleCall;
$assert(
    $watchCall !== null
        && $watchCall['module'] === 'location'
        && $watchCall['method'] === 'watch'
        && Wire::decodeMap($watchCall['payload']) === [
            'distanceFilterMeters' => 20.0,
            'highAccuracy' => true,
            'intervalMs' => 5_000,
        ],
    'Location::watch must emit its typed native watch call.',
);
Runtime::dispatchModuleResult($watchCall['requestId'], ModuleResultStatus::Success->value, Wire::map(['subscription' => 7]));
$nextCall = TestDiagnostics::$moduleCall;
$assert(
    $nextCall['method'] === 'next' && Wire::decodeMap($nextCall['payload']) === ['subscription' => 7],
    'Location::watch must read the native subscription channel.',
);
Runtime::dispatchModuleResult($nextCall['requestId'], ModuleResultStatus::Success->value, Wire::map(['latitude' => -23.5, 'longitude' => -46.6]));
$secondNext = TestDiagnostics::$moduleCall;
$assert($watched === [-23.5] && $secondNext['method'] === 'next' && $secondNext['requestId'] !== $nextCall['requestId'], 'Each watched position must reach the callback and re-arm the next read.');
Location::clearWatch($watchId);
$stopCall = TestDiagnostics::$moduleCall;
$assert(
    $stopCall['method'] === 'stop' && Wire::decodeMap($stopCall['payload']) === ['subscription' => 7] && !Location::watching($watchId),
    'Location::clearWatch must stop the native subscription.',
);
Runtime::dispatchModuleResult($secondNext['requestId'], ModuleResultStatus::Success->value, Wire::map(['latitude' => 1.0, 'longitude' => 1.0]));
$assert($watched === [-23.5], 'A cleared watch must ignore late positions.');
$watchFailure = null;
Location::watch(
    static function (LocationPosition $_): void {
        throw new RuntimeException('A failed watch must not deliver positions.');
    },
    failure: static function (string $message) use (&$watchFailure): void {
        $watchFailure = $message;
    },
);
Runtime::dispatchModuleResult(TestDiagnostics::$moduleCall['requestId'], ModuleResultStatus::Failure->value, 'Location permission is required');
$assert($watchFailure === 'Location permission is required', 'Location::watch start failures must reach the failure callback.');
$lateId = Location::watch(static function (LocationPosition $_): void {});
$lateCall = TestDiagnostics::$moduleCall;
Location::clearWatch($lateId);
Runtime::dispatchModuleResult($lateCall['requestId'], ModuleResultStatus::Success->value, Wire::map(['subscription' => 9]));
$lateStop = TestDiagnostics::$moduleCall;
$assert($lateStop['method'] === 'stop' && Wire::decodeMap($lateStop['payload']) === ['subscription' => 9], 'A watch cleared before the native answer must stop the late subscription.');

require __DIR__.'/location.php';

echo "pam-native-location: {$tests} assertions passed\n";
