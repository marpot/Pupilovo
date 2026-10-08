<?php

namespace Pupilovo\SupplierHub\Domain\Pricing;

defined('ABSPATH') || exit;

final class PricingCalculator {
    /** @return array<string,mixed> */
    public function calculate(array $offer, array $rule, string $store_currency = 'PLN'): array {
        $errors = [];
        $purchase = $this->number($offer['purchasePrice'] ?? null);
        $tax = $this->number($offer['taxRate'] ?? null);
        $currency = strtoupper((string) ($offer['currency'] ?? ''));
        if ($purchase === null || $purchase < 0) { $errors[] = 'missing_or_invalid_purchase_price'; }
        if ($tax === null || $tax < 0 || $tax > 100) { $errors[] = 'missing_or_invalid_tax_rate'; }
        if ($currency === '') { $errors[] = 'missing_currency'; }
        $exchange_rate = $currency === $store_currency ? 1.0 : $this->number($rule['exchangeRate'] ?? null);
        if ($exchange_rate === null || $exchange_rate <= 0) { $errors[] = 'missing_exchange_rate'; }
        if ($errors !== []) { return ['status' => 'decision_required', 'errors' => $errors, 'purchasePrice' => $purchase, 'currency' => $currency]; }

        $price_includes_tax = !empty($offer['priceIncludesTax']);
        $purchase_net_source = $price_includes_tax ? $purchase / (1 + $tax / 100) : $purchase;
        $purchase_net = $purchase_net_source * $exchange_rate;
        $shipping = max(0.0, $this->number($rule['shippingCost'] ?? 0) ?? 0.0);
        $fixed = max(0.0, $this->number($rule['fixedCost'] ?? 0) ?? 0.0);
        $return_buffer = max(0.0, $this->number($rule['returnBufferPercent'] ?? 0) ?? 0.0);
        $payment_percent = max(0.0, $this->number($rule['paymentFeePercent'] ?? 0) ?? 0.0);
        $base_cost = $purchase_net + $shipping + $fixed;
        $base_cost *= 1 + $return_buffer / 100;
        $mode = (string) ($rule['mode'] ?? 'markup');
        $value = $this->number($rule['value'] ?? 0) ?? 0.0;
        if ($mode === 'margin') {
            if ($value < 0 || $value >= 100) { return ['status' => 'decision_required', 'errors' => ['invalid_margin']]; }
            $sale_net = $base_cost / (1 - $value / 100);
        } elseif ($mode === 'markup') {
            if ($value < 0) { return ['status' => 'decision_required', 'errors' => ['invalid_markup']]; }
            $sale_net = $base_cost * (1 + $value / 100);
        } elseif ($mode === 'fixed') {
            $sale_net = $base_cost + $value;
        } else {
            return ['status' => 'decision_required', 'errors' => ['invalid_pricing_mode']];
        }
        if ($payment_percent >= 100) { return ['status' => 'decision_required', 'errors' => ['invalid_payment_fee']]; }
        $sale_net /= 1 - $payment_percent / 100;
        $minimum = max(0.0, $this->number($rule['minimumSalePriceNet'] ?? 0) ?? 0.0);
        $sale_net = max($sale_net, $minimum);
        $sale_gross = $sale_net * (1 + $tax / 100);
        $sale_gross = $this->round_price($sale_gross, is_array($rule['rounding'] ?? null) ? $rule['rounding'] : []);
        $sale_net = $sale_gross / (1 + $tax / 100);
        $profit_net = $sale_net * (1 - $payment_percent / 100) - $base_cost;
        $actual_margin = $sale_net > 0 ? $profit_net / $sale_net * 100 : 0.0;
        $minimum_margin = $this->number($rule['minimumMarginPercent'] ?? null);
        if ($minimum_margin !== null && $actual_margin + 0.0001 < $minimum_margin) { $errors[] = 'minimum_margin_not_met_after_rounding'; }

        return [
            'status' => $errors === [] ? 'calculated' : 'decision_required', 'errors' => $errors,
            'currency' => $store_currency, 'sourceCurrency' => $currency, 'exchangeRate' => $exchange_rate,
            'purchasePriceSource' => round($purchase, 6), 'purchaseCostNet' => round($purchase_net, 6),
            'additionalCostNet' => round($base_cost - $purchase_net, 6), 'salePriceNet' => round($sale_net, 6),
            'salePriceGross' => round($sale_gross, 2), 'taxRate' => $tax, 'estimatedProfitNet' => round($profit_net, 6),
            'estimatedMarginPercent' => round($actual_margin, 4),
            'disclaimer' => 'Kalkulacja nie gwarantuje rentowności i nie uwzględnia kosztów niewprowadzonych w regule.',
        ];
    }

    private function round_price(float $price, array $rounding): float {
        $increment = max(0.01, $this->number($rounding['increment'] ?? 0.01) ?? 0.01);
        $mode = (string) ($rounding['mode'] ?? 'nearest');
        $units = $price / $increment;
        $rounded = match ($mode) { 'up' => ceil($units), 'down' => floor($units), default => round($units) } * $increment;
        if (isset($rounding['ending'])) {
            $ending = $this->number($rounding['ending']);
            if ($ending !== null && $ending >= 0 && $ending < 1) { $rounded = floor($rounded) + $ending; if ($rounded < $price && $mode === 'up') { $rounded += 1; } }
        }

        return round(max(0, $rounded), 2);
    }

    private function number($value): ?float {
        if ($value === null || $value === '') { return null; }
        if (is_string($value)) { $value = str_replace([' ', ','], ['', '.'], $value); }
        return is_numeric($value) ? (float) $value : null;
    }
}
