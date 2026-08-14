<?php
// Tiny Lua interpreter engine (subset) â€” used by tools/lua/index.php
class LuaRunner
{
    private $tokens = [];
    private $pos = 0;
    private $t0;
    private $timeout;
    public $out = '';
    private $depth = 0;
    private $callDepth = 0;
    private static $prec = ['or' => 1, 'and' => 2, '<' => 3, '>' => 3, '<=' => 3, '>=' => 3, '~=' => 3, '==' => 3, '..' => 4, '+' => 5, '-' => 5, '*' => 6, '/' => 6, '%' => 6, '^' => 7];

    public function run(string $code, float $timeout = 4.0): array
    {
        $this->timeout = $timeout;
        $this->t0 = microtime(true);
        $this->out = '';
        try {
            $this->tokens = $this->tokenize($code);
            $this->pos = 0;
            $ast = $this->chunk();
            $env = (object)['vars' => [], 'up' => null];
            $this->seed($env);
            $this->performBlock($ast[1], $env);
            return ['ok' => true, 'out' => $this->out, 'err' => '', 'time' => microtime(true) - $this->t0];
        } catch (Throwable $t) {
            return ['ok' => false, 'out' => $this->out, 'err' => $t->getMessage(), 'time' => microtime(true) - $this->t0];
        }
    }

    private function checkTime(): void
    {
        if (microtime(true) - $this->t0 > $this->timeout) {
            throw new RuntimeException('execution time limit exceeded (' . $this->timeout . 's)');
        }
    }

    private function tokenize(string $code): array
    {
        $toks = [];
        $line = 1;
        $len = strlen($code);
        $i = 0;
        $kw = ['and', 'or', 'not', 'if', 'then', 'elseif', 'else', 'end', 'while', 'do', 'for', 'function', 'local', 'return', 'break', 'nil', 'true', 'false'];
        while ($i < $len) {
            $c = $code[$i];
            if (ctype_space($c)) {
                if ($c === "\n") { $line++; }
                $i++;
                continue;
            }
            if ($c === '-' && ($code[$i + 1] ?? '') === '-') {
                $j = strpos($code, "\n", $i);
                $i = $j === false ? $len : $j;
                continue;
            }
            if ($c === '"' || $c === "'") {
                $q = $c;
                $j = $i + 1;
                $s = '';
                while ($j < $len && $code[$j] !== $q) {
                    if ($code[$j] === '\\') {
                        $j++;
                        $e = $code[$j] ?? '';
                        $s .= $e === 'n' ? "\n" : ($e === 't' ? "\t" : ($e === '\\' ? '\\' : ($e === '"' ? '"' : ($e === "'" ? "'" : $e))));
                        $j++;
                        continue;
                    }
                    $s .= $code[$j];
                    $j++;
                }
                if ($j >= $len) {
                    throw new RuntimeException('Line ' . $line . ': unterminated string');
                }
                $toks[] = ['s', $s, $line];
                $i = $j + 1;
                continue;
            }
            if (ctype_digit($c) || ($c === '.' && ctype_digit($code[$i + 1] ?? ''))) {
                $j = $i;
                $isFloat = false;
                while ($j < $len && (ctype_digit($code[$j]) || $code[$j] === '.')) {
                    if ($code[$j] === '.') { $isFloat = true; }
                    $j++;
                }
                $toks[] = ['n', $isFloat ? (float)substr($code, $i, $j - $i) : (int)substr($code, $i, $j - $i), $line];
                $i = $j;
                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $j = $i;
                while ($j < $len && (ctype_alnum($code[$j]) || $code[$j] === '_')) { $j++; }
                $name = substr($code, $i, $j - $i);
                $toks[] = [in_array($name, $kw, true) ? 'k' : 'id', $name, $line];
                $i = $j;
                continue;
            }
            $two = substr($code, $i, 2);
            if (in_array($two, ['==', '~=', '<=', '>=', '..'], true)) {
                $toks[] = ['op', $two, $line];
                $i += 2;
                continue;
            }
            if (strpos('+-*/%^=<>(){}[],.;#', $c) !== false) {
                $toks[] = ['op', $c, $line];
                $i++;
                continue;
            }
            throw new RuntimeException('Line ' . $line . ': unexpected character "' . $c . '"');
        }
        $toks[] = ['eof', '', $line];
        return $toks;
    }

    private function peek(?string $t = null, $v = null): bool
    {
        $tk = $this->tokens[$this->pos];
        if ($t === null) { return true; }
        return $tk[0] === $t && ($v === null || $tk[1] === $v);
    }

    private function next(): array
    {
        return $this->tokens[$this->pos++];
    }

    private function expect(string $t, string $what): array
    {
        $tk = $this->next();
        if ($tk[0] !== $t) {
            throw new RuntimeException('Line ' . $tk[2] . ': expected ' . $what);
        }
        return $tk;
    }

    private function chunk(): array
    {
        $stmts = [];
        while (!$this->peek('eof')) {
            $s = $this->statement();
            if ($s !== null) { $stmts[] = $s; }
        }
        return ['block', $stmts];
    }

    private function blockUntil(array $stop): array
    {
        $stmts = [];
        while (!$this->peek('eof')) {
            $tk = $this->tokens[$this->pos];
            if ($tk[0] === 'k' && in_array($tk[1], $stop, true)) { break; }
            $s = $this->statement();
            if ($s !== null) { $stmts[] = $s; }
        }
        return $stmts;
    }

    private function statement(): ?array
    {
        $tk = $this->tokens[$this->pos];
        if ($tk[0] === 'op' && $tk[1] === ';') { $this->next(); return null; }
        if ($tk[0] === 'k') {
            switch ($tk[1]) {
                case 'local':
                    $this->next();
                    $names = [$this->expect('id', 'variable name')[1]];
                    while ($this->peek('op', ',')) { $this->next(); $names[] = $this->expect('id', 'variable name')[1]; }
                    $vals = [];
                    if ($this->peek('op', '=')) { $this->next(); $vals = $this->exprlist(); }
                    return ['local', $names, $vals, $tk[2]];
                case 'if':
                    return $this->ifStat($tk);
                case 'while':
                    $this->next();
                    $cond = $this->expr();
                    $this->expect('k', '"do"');
                    $body = $this->blockUntil(['end']);
                    $this->next();
                    return ['while', $cond, $body, $tk[2]];
                case 'for':
                    $this->next();
                    $name = $this->expect('id', 'loop variable')[1];
                    $this->expect('op', '=');
                    $a = $this->expr();
                    $this->expect('op', ',');
                    $b = $this->expr();
                    $step = null;
                    if ($this->peek('op', ',')) { $this->next(); $step = $this->expr(); }
                    $this->expect('k', '"do"');
                    $body = $this->blockUntil(['end']);
                    $this->next();
                    return ['for', $name, $a, $b, $step, $body, $tk[2]];
                case 'function':
                    $this->next();
                    $name = $this->expect('id', 'function name')[1];
                    $fn = $this->funcdef($tk[2]);
                    return ['assign', [['var', $name, $tk[2]]], [$fn], $tk[2]];
                case 'return':
                    $this->next();
                    $vals = ($this->peek('k', 'end') || $this->peek('eof')) ? [] : $this->exprlist();
                    return ['return', $vals, $tk[2]];
                case 'break':
                    $this->next();
                    return ['break', null, $tk[2]];
            }
        }
        $first = $this->prefixExpr();
        if ($this->peek('op', '=') || $this->peek('op', ',')) {
            $vars = [$first];
            while ($this->peek('op', ',')) { $this->next(); $vars[] = $this->prefixExpr(); }
            $this->expect('op', '=');
            $vals = $this->exprlist();
            return ['assign', $vars, $vals, $tk[2]];
        }
        return ['callstmt', $first, $tk[2]];
    }

    private function ifStat(array $tk): array
    {
        $this->next();
        $branches = [];
        $cond = $this->expr();
        $this->expect('k', '"then"');
        $body = $this->blockUntil(['elseif', 'else', 'end']);
        $branches[] = [$cond, $body];
        while ($this->peek('k', 'elseif')) {
            $this->next();
            $c = $this->expr();
            $this->expect('k', '"then"');
            $b = $this->blockUntil(['elseif', 'else', 'end']);
            $branches[] = [$c, $b];
        }
        $els = [];
        if ($this->peek('k', 'else')) { $this->next(); $els = $this->blockUntil(['end']); }
        $this->next();
        return ['if', $branches, $els, $tk[2]];
    }

    private function funcdef(int $line): array
    {
        $this->expect('op', '(');
        $params = [];
        if (!$this->peek('op', ')')) {
            $params[] = $this->expect('id', 'parameter name')[1];
            while ($this->peek('op', ',')) { $this->next(); $params[] = $this->expect('id', 'parameter name')[1]; }
        }
        $this->expect('op', ')');
        $body = $this->blockUntil(['end']);
        $this->next();
        return ['func', $params, $body, $line];
    }

    private function exprlist(): array
    {
        $list = [$this->expr()];
        while ($this->peek('op', ',')) { $this->next(); $list[] = $this->expr(); }
        return $list;
    }

    private function expr(): array
    {
        return $this->binop(0);
    }

    private function binop(int $min): array
    {
        $left = $this->unary();
        while (true) {
            $tk = $this->tokens[$this->pos];
            if ($tk[0] !== 'op' || !isset(self::$prec[$tk[1]])) { break; }
            $p = self::$prec[$tk[1]];
            if ($p < $min) { break; }
            $this->next();
            $right = $this->binop($p + 1);
            $left = ['binop', $tk[1], $left, $right, $tk[2]];
        }
        return $left;
    }

    private function unary(): array
    {
        $tk = $this->tokens[$this->pos];
        if (($tk[0] === 'op' && in_array($tk[1], ['-', '#'], true)) || ($tk[0] === 'k' && $tk[1] === 'not')) {
            $this->next();
            return ['unop', $tk[1], $this->unary(), $tk[2]];
        }
        return $this->simple();
    }

    private function simple(): array
    {
        $tk = $this->tokens[$this->pos];
        if ($tk[0] === 'n') { $this->next(); return ['num', $tk[1], $tk[2]]; }
        if ($tk[0] === 's') { $this->next(); return ['str', $tk[1], $tk[2]]; }
        if ($tk[0] === 'k') {
            if ($tk[1] === 'nil') { $this->next(); return ['nil', null, $tk[2]]; }
            if ($tk[1] === 'true') { $this->next(); return ['bool', true, $tk[2]]; }
            if ($tk[1] === 'false') { $this->next(); return ['bool', false, $tk[2]]; }
            if ($tk[1] === 'function') { $this->next(); return $this->funcdef($tk[2]); }
        }
        if ($tk[0] === 'op' && $tk[1] === '{') { return $this->tblconst(); }
        if ($tk[0] === 'op' && $tk[1] === '(') {
            $this->next();
            $e = $this->expr();
            $this->expect('op', ')');
            return $e;
        }
        return $this->prefixExpr();
    }

    private function prefixExpr(): array
    {
        $tk = $this->expect('id', 'value');
        $node = ['var', $tk[1], $tk[2]];
        while (true) {
            if ($this->peek('op', '(')) {
                $this->next();
                $args = $this->peek('op', ')') ? [] : $this->exprlist();
                $this->expect('op', ')');
                $node = ['call', $node, $args, $tk[2]];
            } elseif ($this->peek('op', '[')) {
                $this->next();
                $k = $this->expr();
                $this->expect('op', ']');
                $node = ['index', $node, $k, $tk[2]];
            } elseif ($this->peek('op', '.')) {
                $this->next();
                $f = $this->expect('id', 'field name')[1];
                $node = ['field', $node, $f, $tk[2]];
            } else {
                break;
            }
        }
        return $node;
    }

    private function tblconst(): array
    {
        $this->expect('op', '{');
        $items = [];
        while (!$this->peek('op', '}')) {
            $next = $this->tokens[$this->pos];
            $after = $this->tokens[$this->pos + 1] ?? $next;
            if ($next[0] === 'op' && $next[1] === '[') {
                $this->next();
                $k = $this->expr();
                $this->expect('op', ']');
                $this->expect('op', '=');
                $items[] = ['pair', $k, $this->expr()];
            } elseif ($next[0] === 'id' && $after[0] === 'op' && $after[1] === '=') {
                $this->next();
                $this->next();
                $items[] = ['pair', ['str', $next[1], $next[2]], $this->expr()];
            } else {
                $items[] = ['list', $this->expr()];
            }
            if ($this->peek('op', ',') || $this->peek('op', ';')) {
                $this->next();
            } else {
                break;
            }
        }
        $this->expect('op', '}');
        return ['tbl', $items, 0];
    }

    private function performBlock(array $stmts, object $env)
    {
        foreach ($stmts as $s) {
            $r = $this->perform($s, $env);
            if ($r === 'break' || (is_array($r) && ($r[0] ?? '') === 'ret')) { return $r; }
        }
        return null;
    }

    private function perform(array $node, object $env)
    {
        if ((++$this->depth & 2047) === 0) { $this->checkTime(); $this->depth = 0; }
        switch ($node[0]) {
            case 'block':
                return $this->performBlock($node[1], $env);
            case 'local':
                $vals = [];
                foreach ($node[2] as $e) { $vals[] = $this->compute($e, $env); }
                foreach ($node[1] as $i => $n) { $env->vars[$n] = $vals[$i] ?? null; }
                return null;
            case 'assign':
                $vals = [];
                foreach ($node[2] as $e) { $vals[] = $this->compute($e, $env); }
                foreach ($node[1] as $i => $v) { $this->assign($v, $vals[$i] ?? null, $env); }
                return null;
            case 'callstmt':
                $this->compute($node[1], $env);
                return null;
            case 'if':
                foreach ($node[1] as $br) {
                    if ($this->truthy($this->compute($br[0], $env))) { return $this->performBlock($br[1], $env); }
                }
                return $this->performBlock($node[2], $env);
            case 'while':
                while ($this->truthy($this->compute($node[1], $env))) {
                    $r = $this->performBlock($node[2], $env);
                    if ($r === 'break') { break; }
                    if (is_array($r)) { return $r; }
                }
                return null;
            case 'for':
                $a = $this->toNum($this->compute($node[2], $env), $node[6]);
                $b = $this->toNum($this->compute($node[3], $env), $node[6]);
                $step = $node[4] === null ? 1 : $this->toNum($this->compute($node[4], $env), $node[6]);
                if ($step == 0) { throw new RuntimeException('Line ' . $node[6] . ': zero step in for loop'); }
                for ($i = $a; ($step > 0 ? $i <= $b : $i >= $b); $i += $step) {
                    $env->vars[$node[1]] = $i;
                    $r = $this->performBlock($node[5], $env);
                    if ($r === 'break') { break; }
                    if (is_array($r)) { return $r; }
                }
                return null;
            case 'func':
                return ['lua_fn', $node[1], $node[2], $env, $node[3]];
            case 'return':
                $vals = [];
                foreach ($node[1] as $e) { $vals[] = $this->compute($e, $env); }
                return ['ret', $vals];
            case 'break':
                return 'break';
        }
        throw new RuntimeException('bad statement');
    }

    private function compute(array $node, object $env)
    {
        if ((++$this->depth & 2047) === 0) { $this->checkTime(); $this->depth = 0; }
        switch ($node[0]) {
            case 'num': return $node[1];
            case 'str': return $node[1];
            case 'nil': return null;
            case 'bool': return $node[1];
            case 'var':
                $v = $this->lookup($node[1], $env);
                if ($v === '&unset') { throw new RuntimeException('Line ' . $node[2] . ': variable "' . $node[1] . '" is nil'); }
                return $v;
            case 'tbl':
                $t = (object)['t' => [], 'n' => 1];
                foreach ($node[1] as $item) {
                    if ($item[0] === 'list') {
                        $v = $this->compute($item[1], $env);
                        $t->t[$t->n] = $v;
                        $t->n++;
                    } else {
                        $t->t[$this->keyStr($this->compute($item[1], $env))] = $this->compute($item[2], $env);
                    }
                }
                return $t;
            case 'index':
                $t = $this->compute($node[1], $env);
                $k = $this->keyStr($this->compute($node[2], $env));
                if ($t instanceof stdClass) { return $t->t[$k] ?? null; }
                if (is_string($t) && is_numeric($k)) {
                    $idx = (int)$k;
                    return ($idx >= 1 && $idx <= strlen($t)) ? $t[$idx - 1] : null;
                }
                throw new RuntimeException('Line ' . $node[3] . ': cannot index this value');
            case 'field':
                $t = $this->compute($node[1], $env);
                if ($t instanceof stdClass) { return $t->t[$node[2]] ?? null; }
                if (is_string($t)) { return $this->strMethod($t, $node[2], $node[3]); }
                throw new RuntimeException('Line ' . $node[3] . ': cannot index this value');
            case 'call':
                $fn = $this->compute($node[1], $env);
                $args = [];
                foreach ($node[2] as $a) { $args[] = $this->compute($a, $env); }
                return $this->callFn($fn, $args, $node[3]);
            case 'unop':
                $v = $this->compute($node[2], $env);
                if ($node[1] === '-') { return -$this->toNum($v, $node[3]); }
                if ($node[1] === 'not') { return !$this->truthy($v); }
                if ($node[1] === '#') {
                    if ($v instanceof stdClass) { return $v->n - 1; }
                    if (is_string($v)) { return strlen($v); }
                    throw new RuntimeException('Line ' . $node[3] . ': length of this value');
                }
                break;
            case 'binop':
                if ($node[1] === 'and') { $l = $this->compute($node[2], $env); return $this->truthy($l) ? $this->compute($node[3], $env) : $l; }
                if ($node[1] === 'or') { $l = $this->compute($node[2], $env); return $this->truthy($l) ? $l : $this->compute($node[3], $env); }
                return $this->applyBinop($node[1], $this->compute($node[2], $env), $this->compute($node[3], $env), $node[4]);
            case 'func':
                return ['lua_fn', $node[1], $node[2], $env, $node[3]];
        }
        throw new RuntimeException('bad expression');
    }

    private function applyBinop(string $op, $l, $r, int $line)
    {
        switch ($op) {
            case '+': return $this->toNum($l, $line) + $this->toNum($r, $line);
            case '-': return $this->toNum($l, $line) - $this->toNum($r, $line);
            case '*': return $this->toNum($l, $line) * $this->toNum($r, $line);
            case '/': return $this->toNum($l, $line) / $this->toNum($r, $line);
            case '%': return fmod($this->toNum($l, $line), $this->toNum($r, $line));
            case '^': return pow($this->toNum($l, $line), $this->toNum($r, $line));
            case '..': return $this->toStr($l) . $this->toStr($r);
            case '==': return $l === $r;
            case '~=': return $l !== $r;
            case '<': return (is_string($l) && is_string($r)) ? $l < $r : $this->toNum($l, $line) < $this->toNum($r, $line);
            case '>': return (is_string($l) && is_string($r)) ? $l > $r : $this->toNum($l, $line) > $this->toNum($r, $line);
            case '<=': return (is_string($l) && is_string($r)) ? $l <= $r : $this->toNum($l, $line) <= $this->toNum($r, $line);
            case '>=': return (is_string($l) && is_string($r)) ? $l >= $r : $this->toNum($l, $line) >= $this->toNum($r, $line);
        }
        return null;
    }

    private function callFn($fn, array $args, int $line)
    {
        if (++$this->callDepth > 500) {
            $this->callDepth--;
            throw new RuntimeException('Line ' . $line . ': stack overflow (too much recursion)');
        }
        try {
            if (is_array($fn) && ($fn[0] ?? '') === 'lua_fn') {
                [, $params, $body, $up] = $fn;
                $env = (object)['vars' => [], 'up' => $up];
                foreach ($params as $i => $p) { $env->vars[$p] = $args[$i] ?? null; }
                $r = $this->performBlock($body, $env);
                if (is_array($r) && ($r[0] ?? '') === 'ret') { return $r[1][0] ?? null; }
                return null;
            }
            if ($fn instanceof Closure) {
                return $fn(...$args);
            }
            throw new RuntimeException('Line ' . $line . ': attempt to call a non-function');
        } finally {
            $this->callDepth--;
        }
    }

    private function assign(array $node, $val, object $env): void
    {
        if ($node[0] === 'var') {
            $e = $env;
            while ($e !== null) {
                if (array_key_exists($node[1], $e->vars)) { $e->vars[$node[1]] = $val; return; }
                $e = $e->up;
            }
            $env->vars[$node[1]] = $val;
            return;
        }
        if ($node[0] === 'index' || $node[0] === 'field') {
            $t = $this->compute($node[1], $env);
            if (!$t instanceof stdClass) { throw new RuntimeException('Line ' . $node[3] . ': cannot index this value'); }
            if ($node[0] === 'index') { $t->t[$this->keyStr($this->compute($node[2], $env))] = $val; }
            else { $t->t[$node[2]] = $val; }
            return;
        }
        throw new RuntimeException('bad assignment target');
    }

    private function lookup(string $name, object $env)
    {
        $e = $env;
        while ($e !== null) {
            if (array_key_exists($name, $e->vars)) { return $e->vars[$name]; }
            $e = $e->up;
        }
        return '&unset';
    }

    private function truthy($v): bool
    {
        return $v !== null && $v !== false;
    }

    private function toNum($v, int $line)
    {
        if (is_int($v) || is_float($v)) { return $v; }
        if (is_string($v) && is_numeric($v)) { return (float)$v; }
        throw new RuntimeException('Line ' . $line . ': expected number, got ' . $this->typeOf($v));
    }

    private function toStr($v): string
    {
        if ($v === null) { return 'nil'; }
        if ($v === true) { return 'true'; }
        if ($v === false) { return 'false'; }
        if ($v instanceof stdClass) { return 'table: 0x' . substr(md5(spl_object_id($v)), 0, 6); }
        if (is_int($v)) { return (string)$v; }
        if (is_float($v)) {
            return ($v == (int)$v) ? (string)(int)$v : rtrim(rtrim(sprintf('%.10f', $v), '0'), '.');
        }
        if (is_string($v)) { return $v; }
        if (is_array($v) && ($v[0] ?? '') === 'lua_fn') { return 'function'; }
        if ($v instanceof Closure) { return 'function'; }
        return (string)$v;
    }

    private function typeOf($v): string
    {
        if ($v === null) { return 'nil'; }
        if ($v === true || $v === false) { return 'boolean'; }
        if (is_int($v) || is_float($v)) { return 'number'; }
        if (is_string($v)) { return 'string'; }
        if ($v instanceof stdClass) { return 'table'; }
        return 'function';
    }

    private function keyStr($v): string
    {
        if (is_int($v) || is_float($v)) { return (string)$v; }
        if (is_string($v)) { return $v; }
        if ($v === true) { return 'true'; }
        if ($v === false) { return 'false'; }
        return 'nil';
    }

    private function strMethod(string $s, string $m, int $line): Closure
    {
        switch ($m) {
            case 'upper': return fn($x = null) => strtoupper($s);
            case 'lower': return fn($x = null) => strtolower($s);
            case 'len': return fn($x = null) => strlen($s);
            case 'rep': return fn($n = 1) => str_repeat($s, max(0, (int)$this->toNum($n, $line)));
            case 'sub': return function ($a = 1, $b = null) use ($s) {
                $len = strlen($s);
                $i = $this->toNum($a, $line);
                $i = $i < 0 ? $len + $i + 1 : $i;
                if ($b === null) { return substr($s, $i - 1); }
                $j = $this->toNum($b, $line);
                $j = $j < 0 ? $len + $j + 1 : $j;
                return substr($s, $i - 1, max(0, $j - $i + 1));
            };
            case 'byte': return fn($i = 1) => ord($s[max(0, (int)$this->toNum($i, $line) - 1)] ?? '');
        }
        throw new RuntimeException('Line ' . $line . ': unknown string method ":' . $m . '"');
    }

    private function seed(object $env): void
    {
        $num = fn($x, $line = 0) => $this->toNum($x, $line);
        $env->vars = [
            'print' => function (...$a) { $p = []; foreach ($a as $x) { $p[] = $this->toStr($x); } $this->out .= implode("\t", $p) . "\n"; return null; },
            'tostring' => fn($x = null) => $this->toStr($x),
            'tonumber' => function ($x = null) { return is_numeric($x) ? (float)$x : null; },
            'type' => fn($x = null) => $this->typeOf($x),
            'floor' => fn($x = null) => (int)floor($num($x, 0)),
            'ceil' => fn($x = null) => (int)ceil($num($x, 0)),
            'abs' => fn($x = null) => abs($num($x, 0)),
            'sqrt' => fn($x = null) => sqrt($num($x, 0)),
            'max' => function (...$a) { $m = null; foreach ($a as $x) { $v = $num($x, 0); if ($m === null || $v > $m) { $m = $v; } } return $m; },
            'min' => function (...$a) { $m = null; foreach ($a as $x) { $v = $num($x, 0); if ($m === null || $v < $m) { $m = $v; } } return $m; },
            'random' => function ($a = 1, $b = null) use ($num) {
                if ($b === null) { return mt_rand() / mt_getrandmax(); }
                return mt_rand((int)$num($a, 0), (int)$num($b, 0));
            },
            'math' => (object)['t' => [
                'floor' => fn($x = null) => (int)floor($num($x, 0)),
                'ceil' => fn($x = null) => (int)ceil($num($x, 0)),
                'abs' => fn($x = null) => abs($num($x, 0)),
                'sqrt' => fn($x = null) => sqrt($num($x, 0)),
                'max' => function (...$a) { $m = null; foreach ($a as $x) { $v = $num($x, 0); if ($m === null || $v > $m) { $m = $v; } } return $m; },
                'min' => function (...$a) { $m = null; foreach ($a as $x) { $v = $num($x, 0); if ($m === null || $v < $m) { $m = $v; } } return $m; },
                'random' => function ($a = 1, $b = null) use ($num) {
                    if ($b === null) { return mt_rand() / mt_getrandmax(); }
                    return mt_rand((int)$num($a, 0), (int)$num($b, 0));
                },
                'pi' => M_PI,
            ], 'n' => 0],
            'string' => (object)['t' => [
                'upper' => fn($s = '') => strtoupper((string)$s),
                'lower' => fn($s = '') => strtolower((string)$s),
                'len' => fn($s = '') => strlen((string)$s),
                'rep' => function ($s = '', $n = 1) use ($num) { return str_repeat((string)$s, max(0, (int)$num($n, 0))); },
                'sub' => function ($s = '', $a = 1, $b = null) use ($num) {
                    $len = strlen((string)$s);
                    $i = $num($a, 0);
                    $i = $i < 0 ? $len + $i + 1 : $i;
                    if ($b === null) { return substr((string)$s, $i - 1); }
                    $j = $num($b, 0);
                    $j = $j < 0 ? $len + $j + 1 : $j;
                    return substr((string)$s, $i - 1, max(0, $j - $i + 1));
                },
                'byte' => function ($s = '', $i = 1) use ($num) { $str = (string)$s; return ord($str[max(0, (int)$num($i, 0) - 1)] ?? ''); },
            ], 'n' => 0],
            'table' => (object)['t' => [
                'insert' => function ($t = null, $v = null) {
                    if (!$t instanceof stdClass) { throw new RuntimeException('table.insert: expected a table'); }
                    $t->t[$t->n] = $v;
                    $t->n++;
                    return null;
                },
                'remove' => function ($t = null, $i = null) use ($num) {
                    if (!$t instanceof stdClass) { throw new RuntimeException('table.remove: expected a table'); }
                    if ($i === null) { $i = $t->n - 1; }
                    $i = (int)$num($i, 0);
                    $v = $t->t[$i] ?? null;
                    unset($t->t[$i]);
                    $t->n--;
                    return $v;
                },
                'concat' => function ($t = null, $sep = '') {
                    if (!$t instanceof stdClass) { throw new RuntimeException('table.concat: expected a table'); }
                    $parts = [];
                    for ($i = 1; $i < $t->n; $i++) { $parts[] = $this->toStr($t->t[$i] ?? null); }
                    return implode((string)$sep, $parts);
                },
                'getn' => fn($t = null) => ($t instanceof stdClass) ? $t->n - 1 : 0,
            ], 'n' => 0],
            '_VERSION' => 'Lua 5.1 (KevBin mini)',
        ];
    }
}