from hello import greeting


def test_default_greeting():
    assert greeting() == "Hello, world!"


def test_named_greeting():
    assert greeting("Marcus") == "Hello, Marcus!"
