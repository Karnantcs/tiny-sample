# tiny-sample

A small sample Python project used to try out the repository workflow.

## Contents

- `hello.py`: prints a greeting (`Hello, world!`). Its `greeting(name)` function returns the greeting for any name.
- `test_hello.py`: pytest tests for `hello.py`.
- `.gitignore`: ignores Python build output, caches, virtual environments and local `.env` files.

## Requirements

- Python 3.8 or newer (no third-party packages needed to run the script).
- pytest, only if you want to run the tests.

## Usage

Print the greeting:

```bash
python hello.py
```

Expected output:

```
Hello, world!
```

Use the function from another script:

```python
from hello import greeting

print(greeting("Marcus"))  # Hello, Marcus!
```

## Running the tests

```bash
pip install pytest
pytest
```
