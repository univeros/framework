<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Parser;

use Altair\Index\Model\ParsedFile;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Parses one PHP file into a {@see ParsedFile} of declared symbols and usages.
 *
 * Names are resolved in a first pass so the second pass sees fully-qualified
 * references; the resulting AST is then walked with parent links so property
 * reads and writes can be told apart. A file that fails to parse yields an
 * empty {@see ParsedFile} (with its hash) rather than aborting a whole build.
 */
final readonly class PhpFileWalker
{
    private Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->createForHostVersion();
    }

    public function walk(string $path, string $code): ParsedFile
    {
        $hash = ParsedFile::hash($code);

        try {
            $ast = $this->parser->parse($code);
        } catch (Error) {
            $ast = null;
        }

        if ($ast === null) {
            return new ParsedFile($path, $hash, [], []);
        }

        $nameResolver = new NodeTraverser();
        $nameResolver->addVisitor(new NameResolver());

        $resolved = $nameResolver->traverse($ast);

        $collector = new SymbolUsageVisitor($path);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor($collector);
        $traverser->traverse($resolved);

        return new ParsedFile($path, $hash, $collector->symbols(), $collector->usages());
    }
}
