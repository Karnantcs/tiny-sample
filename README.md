# tiny-sample

A sample Spring Boot 3.2 application (Java 21) with one REST endpoint.

## Run it

```bash
mvn spring-boot:run
```

## The endpoint

`GET /api/greeting` answers with a greeting in JSON. The optional `name` parameter says who to greet (default: World).

```bash
curl "http://localhost:8080/api/greeting?name=Ada"
# {"message":"Hello, Ada!"}
```

## Test it

```bash
mvn test
```
