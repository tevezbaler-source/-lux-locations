import { DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';

import { Colors } from '@/constants/theme';
import { AppProvider } from '@/lib/app-state';

SplashScreen.preventAutoHideAsync();

const theme = {
  ...DefaultTheme,
  colors: { ...DefaultTheme.colors, background: Colors.light.background, text: Colors.light.text },
};

export default function RootLayout() {
  useEffect(() => {
    SplashScreen.hideAsync();
  }, []);

  return (
    <ThemeProvider value={theme}>
      <AppProvider>
        <StatusBar style="dark" />
        <Stack screenOptions={{ headerTintColor: Colors.light.text, headerBackButtonDisplayMode: 'minimal' }}>
          <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
          <Stack.Screen name="otel/[slug]" options={{ title: '' }} />
          <Stack.Screen name="sayfa" options={{ title: '' }} />
          <Stack.Screen name="panel" options={{ title: 'Panel' }} />
          <Stack.Screen name="dil" options={{ presentation: 'modal', title: '' }} />
        </Stack>
      </AppProvider>
    </ThemeProvider>
  );
}
