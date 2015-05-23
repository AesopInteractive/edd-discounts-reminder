# edd-discounts-reminder

This plugin checks once a day for EDD discount codes that are expiring in the next 24 hours, and sends an email reminder. The name of the code (IE post_title) must be a valid email address. This is the case if they were set with Optin Monster's EDD integration.

Also, this plugin has no UI. For it to work, you must set the configuration via the "edd_option_discount_notifications_config" filter. For example:

```
    add_filter( 'edd_discounts_reminder_config', function() {
        $config = array(
        	'message' => 'message your code is %s',
        	'subject' => 'Discount Code is expiring soon!',
        	'from_email' => 'Josh@CalderaWP.com',
        	'from_name' => 'Josh'
        
        );
        return $config;
        
    });
```

###IMPORTANT
Since the crong job will excute on plugin activation, you must set the filter with the config up, BEFORE, activating the plugin, or the validation of the config will fail. As a result, the plugin is just going to roll its eyes at you like an annoyed teenager, and you will not go to space today.
   
