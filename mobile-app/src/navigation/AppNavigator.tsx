import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '../context/AuthContext';
import { RootStackParamList } from '../types';

// Screens
import LoginScreen from '../screens/LoginScreen';
import MainTabs from './MainTabs';
import DeviceDetailsScreen from '../screens/DeviceDetailsScreen';
import DiagnosticTestScreen from '../screens/DiagnosticTestScreen';
import AlarmDetailsScreen from '../screens/AlarmDetailsScreen';
import QRScannerScreen from '../screens/QRScannerScreen';

const Stack = createNativeStackNavigator<RootStackParamList>();

/**
 * App Navigator
 * 
 * Handles navigation logic based on authentication state
 */

export function AppNavigator() {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    // TODO: Add proper loading screen
    return null;
  }

  return (
    <NavigationContainer>
      <Stack.Navigator
        screenOptions={{
          headerShown: false,
        }}
      >
        {!isAuthenticated ? (
          // Auth Stack
          <Stack.Screen name="Login" component={LoginScreen} />
        ) : (
          // Main App Stack
          <>
            <Stack.Screen name="Main" component={MainTabs} />
            <Stack.Screen
              name="DeviceDetails"
              component={DeviceDetailsScreen}
              options={{ headerShown: true, title: 'Device Details' }}
            />
            <Stack.Screen
              name="DiagnosticTest"
              component={DiagnosticTestScreen}
              options={{ headerShown: true, title: 'Run Diagnostic' }}
            />
            <Stack.Screen
              name="AlarmDetails"
              component={AlarmDetailsScreen}
              options={{ headerShown: true, title: 'Alarm Details' }}
            />
            <Stack.Screen
              name="QRScanner"
              component={QRScannerScreen}
              options={{ headerShown: true, title: 'Scan QR Code' }}
            />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
