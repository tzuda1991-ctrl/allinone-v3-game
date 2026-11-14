#!/usr/bin/env bash
set -euo pipefail
# Exercise screen startup without creating a seat on the production backend.
adb shell svc wifi disable
adb shell svc data disable
adb install "$(find android-app/app/build/outputs/apk/debug -name '*.apk' -print -quit)"
adb install "$(find android-app/app/build/outputs/apk/androidTest -name '*.apk' -print -quit)"
adb logcat -c
adb shell am instrument -w -e class com.gamegards.allinonev3.RummyLaunchTest com.gamegards.allinonev3.test/androidx.test.runner.AndroidJUnitRunner | tee /tmp/rummy-launch.txt
if ! grep -q 'OK (1 test)' /tmp/rummy-launch.txt; then
  adb logcat -d -b crash | tail -150
  exit 1
fi
