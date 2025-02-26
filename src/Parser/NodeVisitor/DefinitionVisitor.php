<?php

declare(strict_types=1);

namespace DePhpViz\Parser\NodeVisitor;

use DePhpViz\Parser\Exception\MultipleDefinitionsException;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that identifies class, trait, and interface definitions.
 */
class DefinitionVisitor extends NodeVisitorAbstract
{
    private ?Node $definition = null;
    private ?string $definitionType = null;
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
            $this->processDefinition($node, 'class');
        } elseif ($node instanceof Interface_) {
            $this->processDefinition($node, 'interface');
        } elseif ($node instanceof Trait_) {
            $this->processDefinition($node, 'trait');
        }

        return null;
    }

    /**
     * Process a definition node, ensuring only one exists per file.
     *
     * @param Node $node The definition node
     * @param string $type The definition type
     * @throws MultipleDefinitionsException If multiple definitions are found
     */
    private function processDefinition(Node $node, string $type): void
    {
        if ($this->definition !== null) {
            throw new MultipleDefinitionsException(
                sprintf('Multiple definitions found in %s', $this->filePath)
            );
        }

        $this->definition = $node;
        $this->definitionType = $type;
    }

    /**
     * Get the definition found in the file.
     */
    public function getDefinition(): ?Node
    {
        return $this->definition;
    }

    /**
     * Get the type of definition found.
     */
    public function getDefinitionType(): ?string
    {
        return $this->definitionType;
    }

    /**
     * Check if a definition was found.
     * @phpstan-assert-if-true Node $this->getDefinition()
     */
    public function hasDefinition(): bool
    {
        return $this->definition !== null;
    }
}
