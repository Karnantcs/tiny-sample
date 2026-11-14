package com.karnantcs.tinysample;

import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

/** The app's one endpoint: GET /api/greeting?name=Ada answers {"message":"Hello, Ada!"}. */
@RestController
public class GreetingController {

    public record Greeting(String message) {}

    @GetMapping("/api/greeting")
    public Greeting greeting(@RequestParam(defaultValue = "World") String name) {
        String who = name.isBlank() ? "World" : name.strip();
        return new Greeting("Hello, " + who + "!");
    }
}
