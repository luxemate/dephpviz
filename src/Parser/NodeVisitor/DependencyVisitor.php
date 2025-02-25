<?php

declare(strict_types=1);

namespace DePhpViz\Parser\NodeVisitor;

use DePhpViz\Parser\Model\Dependency;
use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that extracts class dependencies via use statements and inheritance.
 */
class DependencyVisitor extends NodeVisitorAbstract
{
    /** @var array<Dependency> */
    private array $dependencies = [];

    private string $sourceClass;

    /**
     * @param string $sourceClass The fully qualified name of the class being analyzed
     */
    public function __construct(string $sourceClass)
    {
        $this->sourceClass = $sourceClass;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        // Process use statements
        if ($node instanceof Use_) {
            foreach ($node->uses as $use) {
                $this->addUseStatementDependency($use);
            }
        }

        // Process class inheritance (extends)
        if ($node instanceof Node\Stmt\Class_ && $node->extends !== null) {
            $targetClass = $node->extends->toString();
            $this->dependencies[] = new Dependency(
                $this->sourceClass,
                $targetClass,
                'extends'
            );
        }

        // Process interface implementations
        if ($node instanceof Node\Stmt\Class_ && !empty($node->implements)) {
            foreach ($node->implements as $interface) {
                $targetClass = $interface->toString();
                $this->dependencies[] = new Dependency(
                    $this->sourceClass,
                    $targetClass,
                    'implements'
                );
            }
        }

        return null;
    }

    /**
     * Process a use statement to extract dependency information.
     */
    private function addUseStatementDependency(Node\UseItem $use): void
    {
        $targetClass = $use->name->toString();

        // Skip PHP built-in classes/interfaces
        if (str_starts_with($targetClass, 'PHP')) {
            return;
        }

        $this->dependencies[] = new Dependency(
            $this->sourceClass,
            $targetClass,
            'use'
        );
    }

    /**
     * Get all dependencies found in the file.
     *
     * @return array<Dependency>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
