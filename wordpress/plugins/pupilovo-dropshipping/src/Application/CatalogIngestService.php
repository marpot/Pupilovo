<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Domain\Product\ProductNormalizer;
use Pupilovo\SupplierHub\Infrastructure\Feed\FeedParserFactory;
use Pupilovo\SupplierHub\Infrastructure\Repository\CatalogRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\FeedRunRepository;

defined('ABSPATH') || exit;

final class CatalogIngestService {
    public function __construct(
        private readonly FeedParserFactory $parsers = new FeedParserFactory(),
        private readonly ProductNormalizer $normalizer = new ProductNormalizer(),
        private readonly CatalogRepository $catalog = new CatalogRepository(),
        private readonly FeedRunRepository $runs = new FeedRunRepository()
    ) {}

    /**
     * @return array{feedRunId:int,status:string,completeness:string,seen:int,valid:int,invalid:int,created:int,updated:int,unchanged:int,markedMissing:int,errors:array<int,array<string,mixed>>}
     */
    public function ingest(array $command): array {
        $supplier_id = (int) ($command['supplierId'] ?? 0);
        $source_id = isset($command['sourceId']) ? (int) $command['sourceId'] : null;
        $file = (string) ($command['file'] ?? '');
        $format = strtolower((string) ($command['format'] ?? ''));
        $record_path = (string) ($command['recordPath'] ?? '');
        $mapping = is_array($command['mapping'] ?? null) ? $command['mapping'] : [];
        $parser_config = is_array($command['parserConfig'] ?? null) ? $command['parserConfig'] : [];
        $configuration_hash = (string) ($command['configurationHash'] ?? hash('sha256', wp_json_encode([$format, $record_path, $mapping, $parser_config])));
        $max_records = min(250000, max(1, (int) ($command['maxRecords'] ?? 100000)));
        if ($supplier_id < 1 || !is_readable($file)) {
            throw new \InvalidArgumentException('Nieprawidłowe polecenie importu katalogu.');
        }

        $run_id = $this->runs->begin($supplier_id, $source_id, $configuration_hash, [
            'format' => $format,
            'recordPath' => $record_path,
            'declaredComplete' => !empty($command['declaredComplete']),
        ]);
        $metrics = ['seen' => 0, 'valid' => 0, 'invalid' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0];
        $errors = [];
        try {
            $parser = $this->parsers->create($format, $file, $parser_config);
            foreach ($parser->records($record_path, $max_records) as $index => $record) {
                ++$metrics['seen'];
                $normalized = $this->normalizer->normalize($record, $mapping, (string) ($command['defaultCurrency'] ?? 'PLN'));
                if ($normalized['errors'] !== []) {
                    ++$metrics['invalid'];
                    if (count($errors) < 100) {
                        $errors[] = ['record' => $index + 1, 'codes' => $normalized['errors']];
                    }
                    continue;
                }
                ++$metrics['valid'];
                $result = $this->catalog->upsert($supplier_id, $run_id, $normalized['product'], $record);
                ++$metrics[$result['action']];
            }

            $previous = $this->runs->previous_complete_count($supplier_id, $run_id);
            [$completeness, $reason] = $this->assess_completeness(
                !empty($command['declaredComplete']),
                $metrics,
                $previous,
                (float) ($command['minimumCompletenessRatio'] ?? 0.8)
            );
            $marked_missing = $completeness === 'complete'
                ? $this->catalog->mark_missing_after_complete_run($supplier_id, $run_id)
                : 0;
            $source_checksum = hash_file('sha256', $file) ?: '';
            $this->runs->complete($run_id, [
                'completeness' => $completeness,
                'sourceChecksum' => $source_checksum,
                'seen' => $metrics['seen'],
                'valid' => $metrics['valid'],
                'invalid' => $metrics['invalid'],
                'upserted' => $metrics['created'] + $metrics['updated'],
                'previousCount' => $previous,
                'context' => ['reason' => $reason, 'errors' => $errors, 'markedMissing' => $marked_missing],
            ]);

            return [
                'feedRunId' => $run_id,
                'status' => 'completed',
                'completeness' => $completeness,
                ...$metrics,
                'markedMissing' => $marked_missing,
                'errors' => $errors,
            ];
        } catch (\Throwable $error) {
            $this->runs->fail($run_id, $error->getMessage(), $metrics);
            throw $error;
        }
    }

    /** @return array{string,string} */
    private function assess_completeness(bool $declared, array $metrics, ?int $previous, float $minimum_ratio): array {
        if (!$declared) {
            return ['partial', 'source_not_declared_complete'];
        }
        if ($metrics['seen'] === 0 || $metrics['valid'] === 0) {
            return ['invalid', 'empty_or_no_valid_records'];
        }
        if ($metrics['invalid'] / $metrics['seen'] > 0.05) {
            return ['invalid', 'invalid_record_ratio_above_5_percent'];
        }
        $minimum_ratio = min(1.0, max(0.5, $minimum_ratio));
        if ($previous !== null && $metrics['valid'] < (int) ceil($previous * $minimum_ratio)) {
            return ['suspicious', 'record_count_drop'];
        }

        return ['complete', 'validated'];
    }
}
