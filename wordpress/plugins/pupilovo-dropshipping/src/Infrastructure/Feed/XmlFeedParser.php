<?php

namespace Pupilovo\SupplierHub\Infrastructure\Feed;

defined('ABSPATH') || exit;

final class XmlFeedParser extends AbstractFeedParser {
    public function __construct(private readonly string $file) {
        if (!is_readable($file) || !class_exists('XMLReader')) {
            throw new \InvalidArgumentException('Plik XML jest nieczytelny lub XMLReader jest niedostępny.');
        }
    }

    public function records(string $path, ?int $limit = null): iterable {
        $segments = array_values(array_filter(preg_split('~[/.]+~', trim($path)) ?: []));
        if ($segments === []) {
            throw new \InvalidArgumentException('Podaj ścieżkę powtarzalnego węzła produktu XML.');
        }
        foreach ($segments as $segment) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $segment)) {
                throw new \InvalidArgumentException('Nieprawidłowa ścieżka rekordu XML.');
            }
        }

        $reader = new \XMLReader();
        if (!$reader->open($this->file, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT)) {
            throw new \RuntimeException('Nie można otworzyć XML.');
        }
        $reader->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
        $reader->setParserProperty(\XMLReader::LOADDTD, false);
        $stack = [];
        $count = 0;
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }
                $stack[$reader->depth] = $reader->localName;
                $stack = array_slice($stack, 0, $reader->depth + 1, true);
                $current = array_values($stack);
                if (array_slice($current, -count($segments)) !== $segments) {
                    continue;
                }
                $outer = $reader->readOuterXML();
                if ($outer === '') {
                    continue;
                }
                $previous = libxml_use_internal_errors(true);
                $node = simplexml_load_string($outer, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                if (!$node instanceof \SimpleXMLElement) {
                    throw new \RuntimeException('Nieprawidłowy rekord XML.');
                }
                yield $this->to_array($node);
                ++$count;
                if ($limit !== null && $count >= $limit) {
                    break;
                }
            }
        } finally {
            $reader->close();
        }
    }

    private function to_array(\SimpleXMLElement $node): array {
        $result = [];
        foreach ($node->attributes() as $name => $value) {
            $result['@' . $name] = trim((string) $value);
        }
        $grouped = [];
        foreach ($node->children() as $name => $child) {
            $value = $child->count() > 0 || count($child->attributes()) > 0
                ? $this->to_array($child)
                : trim((string) $child);
            $grouped[$name][] = $value;
        }
        foreach ($grouped as $name => $values) {
            $result[$name] = count($values) === 1 ? $values[0] : $values;
        }
        if ($result === []) {
            return ['#text' => trim((string) $node)];
        }

        return $result;
    }
}
