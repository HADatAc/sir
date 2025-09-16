package tests.STR;

import org.junit.jupiter.api.DisplayName;
import org.junit.jupiter.api.Test;
import tests.base.BaseIngest;

public class STRIngestTest extends BaseIngest {

    @Test
    @DisplayName("Ingest uploaded STR file")
    void shouldIngestSTRSuccessfully() throws InterruptedException {
        ingestFile("str");
    }
}
