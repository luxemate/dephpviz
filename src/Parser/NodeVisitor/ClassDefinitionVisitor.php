<?php

declare(strict_types=1);

namespace DePhpViz\Parser\NodeVisitor;

use DePhpViz\Parser\Exception\MultipleClassesException;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that identifies class definitions and ensures there's exactly one per file.
 */
class ClassDefinitionVisitor extends NodeVisitorAbstract
{
    private ?Class_ $class = null;
    private string $filePath;

    /**
     * @param string $filePath The file path being analyzed
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Class_) {
            if ($this->class !== null) {
                throw new MultipleClassesException(
                    sprintf('Multiple class definitions found in %s', $this->filePath)
                );
            }

            $this->class = $node;
        }

        return null;
    }

    /**
     * Get the class definition found in the file.
     */
    public function getClass(): ?Class_
    {
        return $this->class;
    }

    /**
     * Check if a class was found.
     */
    public function hasClass(): bool
    {
        return $this->class !== null;
    }
}
