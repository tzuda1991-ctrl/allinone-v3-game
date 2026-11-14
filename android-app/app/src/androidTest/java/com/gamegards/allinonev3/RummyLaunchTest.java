package com.gamegards.allinonev3;

import android.app.Activity;
import android.app.Instrumentation;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.view.View;
import androidx.recyclerview.widget.RecyclerView;
import androidx.test.platform.app.InstrumentationRegistry;
import com.gamegards.allinonev3.Fragments.ActiveTables_BF;
import com.gamegards.allinonev3._Rummy.Rummy5Player;
import com.gamegards.allinonev3._TeenPatti.PublicTable;
import java.lang.reflect.Method;
import org.junit.Test;
import static org.junit.Assert.*;

public class RummyLaunchTest {
    @Test public void playFromTwoAndFiveSeatLists() throws Exception {
        final Instrumentation instrumentation = InstrumentationRegistry.getInstrumentation();
        Context context = instrumentation.getTargetContext();
        final SharedPreferences prefs = context.getSharedPreferences(PublicTable.MY_PREFS_NAME, Context.MODE_PRIVATE);
        Intent intent = new Intent(context, Rummy5Player.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        final Rummy5Player lobby = (Rummy5Player) instrumentation.startActivitySync(intent);
        final Method populate = ActiveTables_BF.class.getDeclaredMethod("ParseResponse", String.class);
        populate.setAccessible(true);
        for (boolean twoPlayers : new boolean[]{true, false}) {
            final ActiveTables_BF sheet = new ActiveTables_BF(twoPlayers ? ActiveTables_BF.RUMMY2 : ActiveTables_BF.RUMMY5);
            if (twoPlayers) prefs.edit().putFloat("wallet", 1000f).commit();
            else prefs.edit().putString("wallet", "1,000.00").commit();
            instrumentation.runOnMainSync(new Runnable() {
                @Override public void run() {
                    sheet.showNow(lobby.getSupportFragmentManager(), "tables");
                    try {
                        populate.invoke(sheet, "{\"code\":200,\"table_data\":[{\"boot_value\":\"80.00\",\"point_value\":1}]}");
                    } catch (Exception e) { throw new AssertionError(e); }
                }
            });
            instrumentation.waitForIdleSync();
            Instrumentation.ActivityMonitor monitor = instrumentation.addMonitor(Rummy5Player.class.getName(), null, false);
            instrumentation.runOnMainSync(new Runnable() {
                @Override public void run() {
                    RecyclerView rows = sheet.getDialog().findViewById(R.id.rec_user_points);
                    View row = rows.getLayoutManager().findViewByPosition(0);
                    assertNotNull(row);
                    assertTrue(row.findViewById(R.id.tvPlaynow).performClick());
                }
            });
            final Activity screen = instrumentation.waitForMonitorWithTimeout(monitor, 10000);
            instrumentation.removeMonitor(monitor);
            assertNotNull(screen);
            instrumentation.waitForIdleSync();
            assertNotNull(screen.findViewById(R.id.ivallcard));
            assertEquals(twoPlayers, screen.getIntent().hasExtra("player2"));
            assertEquals("80.00", screen.getIntent().getStringExtra("sel_table"));
            instrumentation.runOnMainSync(new Runnable() {
                @Override public void run() { screen.finish(); }
            });
            instrumentation.waitForIdleSync();
        }
        instrumentation.runOnMainSync(new Runnable() {
            @Override public void run() { lobby.finish(); }
        });
    }
}
