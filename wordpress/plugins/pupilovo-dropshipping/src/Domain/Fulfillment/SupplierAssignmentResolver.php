<?php

namespace Pupilovo\SupplierHub\Domain\Fulfillment;

defined('ABSPATH') || exit;

final class SupplierAssignmentResolver {
    /** @return array{supplierId:?int,productLinkId:?int,reason:string,requiresManualDecision:bool} */
    public function resolve(?int $explicit_supplier_id, array $links, ?string $explicit_external_id = null): array {
        $links = array_values(array_filter($links, static fn($link): bool =>
            is_array($link) && !empty($link['id']) && !empty($link['supplier_id'])
        ));

        if ($explicit_supplier_id !== null && $explicit_supplier_id > 0) {
            $matching = array_values(array_filter($links, static fn(array $link): bool =>
                (int) $link['supplier_id'] === $explicit_supplier_id &&
                ($explicit_external_id === null || $explicit_external_id === '' || (string)($link['external_id'] ?? '') === $explicit_external_id)
            ));
            if (count($matching) === 1) {
                return $this->assigned($matching[0], 'product_supplier_metadata');
            }
            if (count($matching) > 1) { return $this->manual('ambiguous_supplier_metadata_links'); }

            return $this->manual('supplier_metadata_without_active_link');
        }

        $primary = array_values(array_filter(
            $links,
            static fn(array $link): bool => !empty($link['is_primary'])
        ));
        if (count($primary) === 1) {
            return $this->assigned($primary[0], 'primary_product_link');
        }
        if (count($primary) > 1) {
            return $this->manual('multiple_primary_links');
        }
        if (count($links) === 1) {
            return $this->assigned($links[0], 'single_product_link');
        }

        return $this->manual($links === [] ? 'missing_supplier_link' : 'ambiguous_supplier_links');
    }

    private function assigned(array $link, string $reason): array {
        return [
            'supplierId' => (int) $link['supplier_id'],
            'productLinkId' => (int) $link['id'],
            'reason' => $reason,
            'requiresManualDecision' => false,
        ];
    }

    private function manual(string $reason): array {
        return [
            'supplierId' => null,
            'productLinkId' => null,
            'reason' => $reason,
            'requiresManualDecision' => true,
        ];
    }
}
