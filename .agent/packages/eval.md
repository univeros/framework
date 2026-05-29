# univeros/eval  ·  Altair\Eval

**Purpose:** bin/altair eval — a sandboxed scratchpad that executes a short PHP snippet inside the project's container in a guarded subprocess (disable_functions, open_basedir, memory + wall-clock limits) and returns a structured JSON result. The agent's "let me check" primitive.

## Concrete classes

- `EvalCommand` _(final)_
- `EvalConfiguration` _(final)_ — implements `ConfigurationInterface`
- `EvalRequest` _(final)_
- `EvalResult` _(final)_
- `Evaluator` _(final)_
- `ExceptionEncoder` _(final)_
- `Json` _(final)_
- `SecurityProfile` _(final)_
- `SubprocessResult` _(final)_
- `SubprocessRunner` _(final)_
- `ValueEncoder` _(final)_
- `WrapperBuilder` _(final)_

## Tests as documentation

- `tests/Eval/Cli/EvalCommandTest.php`
- `tests/Eval/Encoder/ExceptionEncoderTest.php`
- `tests/Eval/Encoder/ValueEncoderTest.php`
- `tests/Eval/EvaluatorTest.php`
- `tests/Eval/Runner/SecurityProfileTest.php`
- `tests/Eval/Runner/WrapperBuilderTest.php`

## Related packages

- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/events`
