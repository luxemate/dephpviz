<?php

declare(strict_types=1);

namespace DePhpViz\Parser\NodeVisitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that extracts namespace information.
 */
class NamespaceVisitor extends NodeVisitorAbstract
{
    private ?Namespace_ $namespace = null;

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            $this->namespace = $node;
        }

        return null;
    }

    /**
     * Get the namespace definition found in the file.
     */
    public function getNamespace(): ?Namespace_
    {
        return $this->namespace;
    }

    /**
     * Get the namespace name as a string.
     */
    public function getNamespaceName(): string
    {
        if ($this->namespace === null || $this->namespace->name === null) {
            return '';
        }

        return $this->namespace->name->toString();
    }

    /**
     * Check if a namespace was found.
     */
    public function hasNamespace(): bool
    {
        return $this->namespace !== null && $this->namespace->name !== null;
    }
}
