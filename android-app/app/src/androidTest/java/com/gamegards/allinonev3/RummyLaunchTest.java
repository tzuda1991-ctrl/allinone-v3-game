package com.gamegards.allinonev3;

import android.app.Activity;
import android.app.Instrumentation;
import android.content.Intent;
import androidx.test.platform.app.InstrumentationRegistry;
import com.gamegards.allinonev3._Rummy.Rummy5Player;
import org.junit.Test;
import static org.junit.Assert.assertNotNull;

public class RummyLaunchTest {
    @Test public void openTwoAndFiveSeatTables() {
        Instrumentation instrumentation = InstrumentationRegistry.getInstrumentation();
        for (boolean twoPlayers : new boolean[]{true, false}) {
            Intent intent = new Intent(instrumentation.getTargetContext(), Rummy5Player.class);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            intent.putExtra("sel_table", "80.00");
            intent.putExtra("min_entry", 100f);
            if (twoPlayers) intent.putExtra("player2", "player2");
            final Activity screen = instrumentation.startActivitySync(intent);
            instrumentation.waitForIdleSync();
            assertNotNull(screen.findViewById(R.id.ivallcard));
            instrumentation.runOnMainSync(new Runnable() {
                @Override public void run() { screen.finish(); }
            });
            instrumentation.waitForIdleSync();
        }
    }
}
