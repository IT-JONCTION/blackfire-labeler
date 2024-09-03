# Blackfire Labeler Task Runner

## Overview

This repository provides a utility to facilitate the labeling of transactions using Blackfire and manage their associated data in Redis. The primary components include a task runner, a Blackfire labeler, and a Redis configuration class. The setup is intentionally small and siloed to handle only the limited functionality required for Blackfire profiling and Redis data management. This design is deliberate because the code is intended to be called from an `auto_prepend` file. By keeping it isolated, we avoid potential conflicts with the `RedisConfig` class or other configurations used in different parts of the project or other projects.

## Project Structure

```plaintext
.
├── src
│   ├── ItJonction
│   │   └── BlackfireLabeler
│   │       ├── BlackfireLabelerController.php
│   │       ├── CommonTaskRunner.php
│   │       ├── BlackfireLabeler.php
│   │       └── RedisConfig.php
├── redisValues.php
├── composer.json
└── README.md
```

### Main Components

- **src/ItJonction/BlackfireLabeler/BlackfireLabelerController.php**: This script serves as the entry point for running Blackfire-related tasks. It triggers the `runBlackfireTask` function, which handles labeling and profiling.

- **src/ItJonction/BlackfireLabeler/CommonTaskRunner.php**: This file contains the `runBlackfireTask` function, which is responsible for executing tasks with a Blackfire labeler. It handles loading necessary classes and managing errors during the task execution.

- **src/ItJonction/BlackfireLabeler/BlackfireLabeler.php**: The `BlackfireLabeler` class provides various methods to label transactions, log request details to Redis, and set transaction names for profiling.

- **src/ItJonction/BlackfireLabeler/RedisConfig.php**: The `RedisConfig` class is responsible for configuring and providing access to the Redis client. It allows connection management and selection of the appropriate Redis database based on the environment configuration.

## Installation

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/your-repo/blackfire-labeler-task-runner.git
   cd blackfire-labeler-task-runner
   ```

2. **Install Composer Dependencies:**
   ```bash
   composer install
   ```

3. **Configure Redis Settings:**
   - Edit the `redisValues.php` file to include your Redis server configuration:
   ```php
   <?php
   $scopedRedisEnvValues = [
       'REDIS_HOST' => 'your-redis-host',
       'REDIS_PORT' => your-redis-port, // Replace with actual port number
       'REDIS_USERNAME' => 'your-redis-username',
       'REDIS_PASSWORD' => 'your-redis-password',
       'REDIS_DB' => 0, // Replace with actual database number
       'REDIS_SECURE' => true, // or false, based on your configuration
   ];
   ```

   This configuration will be automatically loaded when the `runBlackfireTask` function is called.

## Usage

### Configuring the Auto Prepend File

To enable Blackfire profiling and Redis data management for your PHP application, you need to reference the `BlackfireLabelerController.php` file in the `auto_prepend_file` directive of your `php.ini` configuration.

Add the following line to your live `php.ini` file:

```ini
auto_prepend_file = /your/path/to/src/ItJonction/BlackfireLabeler/BlackfireLabelerController.php
```

This setup ensures that the Blackfire labeling and Redis logging tasks are automatically executed at the start of each request, before any other PHP scripts run.

## Customizing Redis Configuration

- The `RedisConfig` class provides flexibility to adjust the connection settings for Redis based on the values defined in `redisValues.php`. This includes options for host, port, authentication, and database selection.

## Logging and Profiling

- **BlackfireLabeler** offers methods for:
  - Generating unique request hashes.
  - Logging request details and included files to Redis.
  - Setting transaction names for Blackfire profiling.
  - Archiving and managing logs.

## Contributing

Contributions to this repository are welcome. Please fork the repository and create a pull request for any feature enhancements or bug fixes. Ensure that your code adheres to the project's coding standards and includes appropriate tests.

## License

This project is licensed under the MIT License. See the `LICENSE` file for more details.

## Support

If you encounter any issues or have questions, please open an issue in the repository or contact the maintainers.

---

This README now reflects the simplified project structure and continues to provide clear instructions for setup, usage, and configuration.
