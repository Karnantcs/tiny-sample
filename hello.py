"""Print a greeting."""


def greeting(name: str = "world") -> str:
    """Return a greeting for the given name."""
    return f"Hello, {name}!"


def main() -> None:
    print(greeting())


if __name__ == "__main__":
    main()
