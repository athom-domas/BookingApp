<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function accrue(Appointment $appointment, float $amount): void
    {
        $business = \App\Models\Business::find($appointment->business_id);
        if (! $business?->canUseFeature('loyalty_program')) {
            return;
        }

        if (! SystemSetting::isLoyaltyEnabled()) {
            return;
        }

        $alreadyEarned = LoyaltyTransaction::where('appointment_id', $appointment->id)
            ->where('type', 'earn')
            ->exists();
        if ($alreadyEarned) {
            return;
        }

        $points = (int) floor($amount * SystemSetting::getLoyaltyPointsPerEuro());
        if ($points <= 0) {
            return;
        }

        try {
            DB::transaction(function () use ($appointment, $points) {
                $account = LoyaltyAccount::firstOrCreate([
                    'user_id'     => $appointment->user_id,
                    'business_id' => $appointment->business_id,
                ]);
                LoyaltyTransaction::create([
                    'loyalty_account_id' => $account->id,
                    'appointment_id'     => $appointment->id,
                    'type'               => 'earn',
                    'points'             => $points,
                    'description'        => "Pagamento appuntamento #{$appointment->id}",
                ]);
                $account->increment('points', $points);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Solo la violazione del vincolo unico (earn concorrente) è un no-op idempotente.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    public function redeem(Appointment $appointment, ?array $tier = null): array
    {
        if (! SystemSetting::isLoyaltyEnabled()) {
            return ['percentage' => 0, 'amount' => null];
        }

        $alreadyRedeemed = LoyaltyTransaction::where('appointment_id', $appointment->id)
            ->where('type', 'redeem')
            ->exists();
        if ($alreadyRedeemed) {
            return ['percentage' => 0, 'amount' => null];
        }

        $account = LoyaltyAccount::where('user_id', $appointment->user_id)->first();
        if (! $account) {
            return ['percentage' => 0, 'amount' => null];
        }

        if ($tier === null) {
            $available = SystemSetting::getAvailableTiers($account->points);
            $tier = ! empty($available) ? $available[0] : null;
        }

        if (! $tier) {
            return ['percentage' => 0, 'amount' => null];
        }

        $threshold  = (int) ($tier['threshold'] ?? 0);
        $percentage = (int) ($tier['percentage'] ?? 0);
        $amount     = isset($tier['amount']) ? (float) $tier['amount'] : null;

        DB::transaction(function () use ($account, $appointment, $threshold) {
            LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'appointment_id'     => $appointment->id,
                'type'               => 'redeem',
                'points'             => -$threshold,
                'description'        => "Sconto fedeltà appuntamento #{$appointment->id}",
            ]);
            $account->decrement('points', $threshold);
        });

        return [
            'percentage' => $percentage,
            'amount'     => $amount,
        ];
    }

    public function reverse(Appointment $appointment): void
    {
        // Storna solo l'accredito: un voucher già riscattato resta consumato.
        $earn = LoyaltyTransaction::where('appointment_id', $appointment->id)
            ->where('type', 'earn')
            ->first();
        if (! $earn) {
            return;
        }

        $alreadyReversed = LoyaltyTransaction::where('appointment_id', $appointment->id)
            ->where('type', 'reverse')
            ->exists();
        if ($alreadyReversed) {
            return;
        }

        $account = LoyaltyAccount::find($earn->loyalty_account_id);
        if (! $account) {
            return;
        }

        DB::transaction(function () use ($account, $appointment, $earn) {
            LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'appointment_id'     => $appointment->id,
                'type'               => 'reverse',
                'points'             => -$earn->points,
                'description'        => "Storno punti appuntamento #{$appointment->id}",
            ]);
            $account->update(['points' => max(0, $account->points - $earn->points)]);
        });
    }
}
