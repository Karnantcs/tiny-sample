package com.karnantcs.tinysample;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.WebMvcTest;
import org.springframework.test.web.servlet.MockMvc;

@WebMvcTest(GreetingController.class)
class GreetingControllerTest {

    @Autowired
    private MockMvc mvc;

    @Test
    void greetsTheWorldByDefault() throws Exception {
        mvc.perform(get("/api/greeting"))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.message").value("Hello, World!"));
    }

    @Test
    void greetsByName() throws Exception {
        mvc.perform(get("/api/greeting").param("name", "Ada"))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.message").value("Hello, Ada!"));
    }

    @Test
    void aBlankNameMeansTheWorld() throws Exception {
        mvc.perform(get("/api/greeting").param("name", "  "))
            .andExpect(status().isOk())
            .andExpect(jsonPath("$.message").value("Hello, World!"));
    }
}
