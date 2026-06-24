<?php

use App\Services\PhoneNormalizer;

it('normalizes italian mobile without prefix', function () {
    expect(PhoneNormalizer::normalize('3401234567'))->toBe('+393401234567');
});

it('normalizes with leading zero', function () {
    expect(PhoneNormalizer::normalize('03401234567'))->toBe('+393401234567');
});

it('keeps E.164 unchanged', function () {
    expect(PhoneNormalizer::normalize('+393401234567'))->toBe('+393401234567');
});

it('strips 0039 prefix', function () {
    expect(PhoneNormalizer::normalize('00393401234567'))->toBe('+393401234567');
});

it('strips non-numeric chars', function () {
    expect(PhoneNormalizer::normalize('+39 340 123 4567'))->toBe('+393401234567');
});
