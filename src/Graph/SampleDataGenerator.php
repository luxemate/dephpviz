<?php

declare(strict_types=1);

namespace DePhpViz\Graph;

use DePhpViz\Parser\Model\ClassDefinition;
use DePhpViz\Parser\Model\Dependency;

/**
 * Generates sample data for testing graph construction.
 */
class SampleDataGenerator
{
    /**
     * Generate a simple hierarchy of classes with dependencies.
     *
     * @param int $depth Depth of the hierarchy
     * @param int $width Number of children per class
     * @return array<array{class: ClassDefinition, dependencies: array<Dependency>}>
     */
    public function generateClassHierarchy(int $depth = 3, int $width = 3): array
    {
        $result = [];

        // Create base class
        $baseClassName = 'BaseClass';
        $baseNamespace = 'App\\Base';
        $baseFqcn = $baseNamespace . '\\' . $baseClassName;

        $baseClass = new ClassDefinition(
            $baseClassName,
            $baseNamespace,
            $baseFqcn,
            '/path/to/BaseClass.php',
            ['Base class documentation'],
            true,
            false
        );

        $result[] = [
            'class' => $baseClass,
            'dependencies' => []
        ];

        // Generate hierarchy
        $this->generateHierarchyLevel(
            $result,
            $baseFqcn,
            $depth,
            $width,
            1,
            0
        );

        return $result;
    }

    /**
     * Generate a specific level in the class hierarchy.
     *
     * @param array<array{class: ClassDefinition, dependencies: array<Dependency>}> $result
     * @param string $parentFqcn
     * @param int $maxDepth
     * @param int $width
     * @param int $currentDepth
     * @param int $index
     */
    private function generateHierarchyLevel(
        array &$result,
        string $parentFqcn,
        int $maxDepth,
        int $width,
        int $currentDepth,
        int $index
    ): void {
        if ($currentDepth > $maxDepth) {
            return;
        }

        for ($i = 0; $i < $width; $i++) {
            $className = "Class_{$currentDepth}_{$index}_{$i}";
            $namespace = "App\\Level{$currentDepth}";
            $fqcn = $namespace . '\\' . $className;

            $class = new ClassDefinition(
                $className,
                $namespace,
                $fqcn,
                "/path/to/{$className}.php",
                ["Class at depth {$currentDepth}"],
                false,
                false
            );

            // This class extends its parent
            $dependencies = [
                new Dependency($fqcn, $parentFqcn, 'extends')
            ];

            // Add some cross-dependencies between siblings
            if ($i > 0) {
                $siblingFqcn = $namespace . "\\Class_{$currentDepth}_{$index}_" . ($i - 1);
                $dependencies[] = new Dependency($fqcn, $siblingFqcn, 'use');
            }

            $result[] = [
                'class' => $class,
                'dependencies' => $dependencies
            ];

            // Generate next level
            $this->generateHierarchyLevel(
                $result,
                $fqcn,
                $maxDepth,
                $width,
                $currentDepth + 1,
                $i
            );
        }
    }

    /**
     * Generate a complex network with varying connection patterns.
     *
     * @param int $nodeCount Number of nodes to generate
     * @param float $connectivityFactor 0-1 value indicating how connected the network should be
     * @return array<array{class: ClassDefinition, dependencies: array<Dependency>}>
     */
    public function generateComplexNetwork(int $nodeCount = 50, float $connectivityFactor = 0.3): array
    {
        $result = [];
        $classes = [];

        // Create classes
        for ($i = 0; $i < $nodeCount; $i++) {
            $className = "NetworkNode{$i}";
            $namespace = "App\\Network";
            $fqcn = $namespace . '\\' . $className;

            $class = new ClassDefinition(
                $className,
                $namespace,
                $fqcn,
                "/path/to/{$className}.php",
                ["Network node {$i}"],
                false,
                false
            );

            $classes[$fqcn] = $class;
            $result[$fqcn] = [
                'class' => $class,
                'dependencies' => []
            ];
        }

        // Create dependencies with specified connectivity
        $classFqcns = array_keys($classes);
        $maxPossibleConnections = $nodeCount * ($nodeCount - 1);
        $targetConnectionCount = (int)($maxPossibleConnections * $connectivityFactor);

        $currentConnections = 0;
        $maxAttempts = $targetConnectionCount * 2;
        $attempts = 0;

        while ($currentConnections < $targetConnectionCount && $attempts < $maxAttempts) {
            $sourceIndex = mt_rand(0, $nodeCount - 1);
            $targetIndex = mt_rand(0, $nodeCount - 1);

            if ($sourceIndex !== $targetIndex) {
                $sourceFqcn = $classFqcns[$sourceIndex];
                $targetFqcn = $classFqcns[$targetIndex];

                // Check if this dependency already exists
                $exists = false;
                foreach ($result[$sourceFqcn]['dependencies'] as $dependency) {
                    if ($dependency->targetClass === $targetFqcn) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $type = mt_rand(0, 10) === 0 ? 'extends' : 'use';

                    // Ensure we don't create circular inheritance
                    if ($type === 'extends') {
                        $canExtend = true;
                        // Check if target already extends source directly or indirectly
                        $visited = [$targetFqcn => true];
                        $queue = [$targetFqcn];

                        while (!empty($queue)) {
                            $current = array_shift($queue);
                            foreach ($result[$current]['dependencies'] as $dependency) {
                                if ($dependency->type === 'extends') {
                                    if ($dependency->targetClass === $sourceFqcn) {
                                        $canExtend = false;
                                        break 2;
                                    }

                                    if (!isset($visited[$dependency->targetClass]) && isset($result[$dependency->targetClass])) {
                                        $visited[$dependency->targetClass] = true;
                                        $queue[] = $dependency->targetClass;
                                    }
                                }
                            }
                        }

                        if (!$canExtend) {
                            $type = 'use';
                        }
                    }

                    $result[$sourceFqcn]['dependencies'][] = new Dependency($sourceFqcn, $targetFqcn, $type);
                    $currentConnections++;
                }
            }

            $attempts++;
        }

        // Convert to the expected format
        $finalResult = [];
        foreach ($result as $item) {
            $finalResult[] = $item;
        }

        return $finalResult;
    }
}
