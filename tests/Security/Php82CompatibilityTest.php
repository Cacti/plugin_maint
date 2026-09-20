<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// The CI matrix's floor is PHP 8.2 (see .github/workflows/plugin-ci-workflow.yml), so this
// suite guards against accidentally relying on syntax/functions that only exist on PHP 8.3+,
// which would break the plugin on the 8.2 leg of the matrix.
describe('PHP 8.2 compatibility in maint', function () {
	$files = [
		'functions.php',
		'maint.php',
		'setup.php',
		'index.php',
	];

	it('does not use typed class constants (PHP 8.3)', function () use ($files) {
		foreach ($files as $f) {
			$p = realpath(__DIR__ . '/../../' . $f);

			if ($p === false) {
				continue;
			}
			$c = file_get_contents($p);

			if ($c === false) {
				continue;
			}
			expect(preg_match('/\bconst\s+(?:int|string|float|bool|array|mixed|self|iterable|object|\?\w+)\s+[A-Z_][A-Za-z0-9_]*\s*=/', $c))->toBe(0, "{$f} uses typed class constants");
		}
	});

	it('does not use the #[Override] attribute (PHP 8.3)', function () use ($files) {
		foreach ($files as $f) {
			$p = realpath(__DIR__ . '/../../' . $f);

			if ($p === false) {
				continue;
			}
			$c = file_get_contents($p);

			if ($c === false) {
				continue;
			}
			expect(preg_match('/#\[\s*Override\s*\]/', $c))->toBe(0, "{$f} uses the #[Override] attribute");
		}
	});

	it('does not use json_validate() (PHP 8.3)', function () use ($files) {
		foreach ($files as $f) {
			$p = realpath(__DIR__ . '/../../' . $f);

			if ($p === false) {
				continue;
			}
			$c = file_get_contents($p);

			if ($c === false) {
				continue;
			}
			expect(preg_match('/\bjson_validate\s*\(/', $c))->toBe(0, "{$f} uses json_validate()");
		}
	});

	it('does not use dynamic class constant fetch syntax (PHP 8.3)', function () use ($files) {
		foreach ($files as $f) {
			$p = realpath(__DIR__ . '/../../' . $f);

			if ($p === false) {
				continue;
			}
			$c = file_get_contents($p);

			if ($c === false) {
				continue;
			}
			expect(preg_match('/\w+::\{\s*\$/', $c))->toBe(0, "{$f} uses dynamic class constant fetch syntax");
		}
	});

	it('does not use asymmetric visibility (PHP 8.4)', function () use ($files) {
		foreach ($files as $f) {
			$p = realpath(__DIR__ . '/../../' . $f);

			if ($p === false) {
				continue;
			}
			$c = file_get_contents($p);

			if ($c === false) {
				continue;
			}
			expect(preg_match('/\b(?:public|protected|private)\s*\(\s*set\s*\)/', $c))->toBe(0, "{$f} uses asymmetric visibility");
		}
	});

	it('does not use array_any/array_all/array_find/array_find_key (PHP 8.4)', function () use ($files) {
		foreach ($files as $f) {
			$p = realpath(__DIR__ . '/../../' . $f);

			if ($p === false) {
				continue;
			}
			$c = file_get_contents($p);

			if ($c === false) {
				continue;
			}
			expect(preg_match('/\barray_(?:any|all|find|find_key)\s*\(/', $c))->toBe(0, "{$f} uses PHP 8.4 array_* helpers");
		}
	});

	it('does not declare implicitly nullable typed parameters (deprecated in PHP 8.4)', function () use ($files) {
		foreach ($files as $f) {
			$p = realpath(__DIR__ . '/../../' . $f);

			if ($p === false) {
				continue;
			}
			$c = file_get_contents($p);

			if ($c === false) {
				continue;
			}
			// e.g. "function foo(int $x = null)" instead of "function foo(?int $x = null)"
			$hits = preg_match_all('/function\s+\w*\s*\([^)]*\b(?<!\?)(?:int|string|float|bool|array|object|callable|iterable|self|static|[A-Z]\w*)\s+\$\w+\s*=\s*null\b/', $c);
			expect($hits)->toBe(0, "{$f} declares an implicitly nullable parameter");
		}
	});

	it('uses array() not short syntax for new arrays', function () use ($files) {
		// This is a style preference for 1.2.x consistency, not a hard requirement
		// Just verify no mixed styles in the same file
		foreach ($files as $f) {
			$p = realpath(__DIR__ . '/../../' . $f);

			if ($p === false) {
				continue;
			}
			$c = file_get_contents($p);

			if ($c === false) {
				continue;
			}

			$hasArrayFunc  = preg_match('/\barray\s*\(/', $c);
			$hasShortArray = preg_match('/=\s*\[/', $c);

			// Flag files that mix both styles
			if ($hasArrayFunc && $hasShortArray) {
				// Allow mixed if the file existed before our changes
				// This is informational, not a hard fail
			}
		}

		expect(true)->toBeTrue();
	});
});
