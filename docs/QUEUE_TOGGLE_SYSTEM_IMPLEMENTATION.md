# Queue Toggle System Implementation

## Summary
Implemented a comprehensive queue settings management system to enable runtime control of queue operations for testing purposes.

## Components Created

### 1. QueueSettingsService (`utils/queue_settings_service.php`)
- **Purpose**: Central service for managing queue system configuration
- **Key Methods**:
  - `getSetting()` - Get setting value with caching
  - `updateSetting()` - Update setting with database persistence
  - `toggleSetting()` - Toggle boolean settings
  - `getSystemStatus()` - Get current system configuration
  - `isTestingMode()` - Check if testing mode is active
  - `shouldIgnoreTimeConstraints()` - Check if time restrictions should be bypassed
  - `isQueueOverrideModeEnabled()` - Check if manual overrides are allowed
  - `shouldForceAllStationsOpen()` - Check if all stations should be available

### 2. Queue Settings API (`pages/queueing/queue_settings_api.php`)
- **Purpose**: AJAX endpoint for queue settings management
- **Security**: Admin-only access with session validation
- **Endpoints**:
  - `get_status` - Retrieve current system status
  - `toggle_testing_mode` - Enable/disable testing mode
  - `toggle_time_constraints` - Enable/disable time constraint bypass
  - `toggle_override_mode` - Enable/disable queue override capabilities  
  - `toggle_force_stations` - Force all stations to remain open
  - `update_setting` - Update individual settings
  - `get_all_settings` - Retrieve all configuration settings

### 3. Admin Dashboard Integration
- **UI Components**: Toggle switches for each setting
- **Real-time Updates**: JavaScript management with AJAX calls
- **Status Indicators**: Visual feedback for current system state
- **Settings Panel**: Dedicated section in admin dashboard

### 4. Database Table (`database/queue_settings_table.sql`)
- **Table**: `queue_settings`
- **Structure**: 
  - `setting_id` - Auto-increment primary key
  - `setting_key` - Unique setting identifier  
  - `setting_value` - Configuration value
  - `enabled` - Active status flag
  - `updated_at` - Last modification timestamp
  - `created_at` - Creation timestamp

## Toggle Features

### Testing Mode
- **Purpose**: Enable comprehensive testing without operational restrictions
- **Effects**: Bypasses business hours, enables all overrides, forces stations open

### Time Constraints
- **Purpose**: Ignore business hours and scheduling restrictions
- **Effects**: Allows queue operations outside normal CHO hours (7 AM - 5 PM, Mon-Fri)

### Override Mode  
- **Purpose**: Enable manual queue interventions
- **Effects**: Allows staff to skip, recall, or reassign patients in queue

### Force Stations Open
- **Purpose**: Keep all stations available regardless of schedule
- **Effects**: Bypasses staff assignment checks and station availability rules

## JavaScript Integration

### QueueSettingsManager Class
- **Auto-initialization**: Loads current settings on dashboard load
- **Event Handling**: Manages toggle switch interactions
- **AJAX Communication**: Handles API calls and responses
- **UI Updates**: Synchronizes interface with server state
- **Error Management**: Provides user feedback on failures

### Visual Feedback
- **Status Indicators**: Color-coded system status display
- **Toggle Animation**: Smooth switch transitions
- **Loading States**: Progress indicators during operations
- **Success/Error Messages**: Contextual feedback for actions

## Usage Instructions

### For Testing
1. Access Admin Dashboard
2. Navigate to "Queue System Settings" panel
3. Toggle desired testing options:
   - **Testing Mode**: Full testing environment
   - **Time Constraints**: Allow operations outside business hours
   - **Override Mode**: Enable manual queue interventions
   - **Force Stations**: Keep all stations available

### For Production
- All toggles should be OFF during normal operations
- Only enable specific overrides when needed for troubleshooting
- Monitor system status indicator for current configuration

## Integration Points

### Queue Operations
- All queue services should check `QueueSettingsService` before applying restrictions
- Time-based validations should respect `shouldIgnoreTimeConstraints()`
- Station availability should consider `shouldForceAllStationsOpen()`

### Station Management  
- Assignment validation should check override settings
- Business hours enforcement should respect time constraint settings
- Manual interventions should verify `isQueueOverrideModeEnabled()`

### Appointment System
- Booking restrictions should check testing mode status
- Time slot validation should respect constraint settings
- Queue integration should use current configuration

## Security Considerations

### Access Control
- **Admin Only**: All toggle operations restricted to admin role
- **Session Validation**: Requires active employee session
- **AJAX Protection**: X-Requested-With header validation
- **Audit Logging**: All configuration changes logged with employee ID

### Data Integrity
- **Database Constraints**: Unique setting keys prevent duplicates
- **Default Values**: Fallback values for missing settings
- **Transaction Safety**: Atomic updates with error handling
- **Cache Management**: Automatic cache invalidation on updates

## Future Enhancements

### Planned Features
- **Scheduled Toggles**: Automatic enable/disable based on time
- **Bulk Configuration**: Preset configurations for different scenarios
- **Permission Levels**: Granular access control for different admin levels
- **Configuration History**: Track changes over time with rollback capability

### Integration Opportunities
- **Mobile App Settings**: Extend toggles to mobile applications
- **External Systems**: API endpoints for third-party integrations  
- **Monitoring Alerts**: Notifications when non-standard configurations are active
- **Performance Metrics**: Impact analysis of different configuration states

This system provides essential testing infrastructure while maintaining production security and operational integrity.