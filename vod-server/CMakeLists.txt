cmake_minimum_required(VERSION 3.10)
project(vod-server C)

set(CMAKE_C_STANDARD 11)
set(CMAKE_C_STANDARD_REQUIRED ON)

# Version
set(VOD_SERVER_VERSION_MAJOR 1)
set(VOD_SERVER_VERSION_MINOR 0)
set(VOD_SERVER_VERSION_PATCH 0)

# Build type defaults to Release
if(NOT CMAKE_BUILD_TYPE)
    set(CMAKE_BUILD_TYPE Release)
endif()

# Compiler flags
set(CMAKE_C_FLAGS "${CMAKE_C_FLAGS} -Wall -Wextra -Wno-unused-parameter -Wno-format-truncation")
set(CMAKE_C_FLAGS_DEBUG "-g -O0 -DDEBUG")
set(CMAKE_C_FLAGS_RELEASE "-O2 -DNDEBUG")

# Configure version header
configure_file(
    "${CMAKE_SOURCE_DIR}/src/version.h.in"
    "${CMAKE_BINARY_DIR}/version.h"
)

# Find required libraries
find_package(PkgConfig REQUIRED)

# libmicrohttpd
pkg_check_modules(MHD REQUIRED libmicrohttpd)

# SQLite3
pkg_check_modules(SQLITE3 REQUIRED sqlite3)

# GnuTLS (for SSL support via libmicrohttpd)
pkg_check_modules(GNUTLS gnutls)

# pthreads
find_package(Threads REQUIRED)

# Source files
set(SOURCES
    src/main.c
    src/config.c
    src/logger.c
    src/database.c
    src/http_server.c
    src/api_routes.c
    src/web_routes.c
    src/job_processor.c
    src/transcoder.c
    src/packager.c
    src/storage.c
    src/thumbnails.c
    src/ssl.c
    src/cluster.c
    src/migration.c
    include/cjson/cJSON.c
    include/inih/ini.c
)

# Include directories
include_directories(
    ${CMAKE_SOURCE_DIR}/src
    ${CMAKE_SOURCE_DIR}/include
    ${CMAKE_BINARY_DIR}
    ${MHD_INCLUDE_DIRS}
    ${SQLITE3_INCLUDE_DIRS}
)

if(GNUTLS_FOUND)
    include_directories(${GNUTLS_INCLUDE_DIRS})
    add_definitions(-DHAVE_GNUTLS)
endif()

# Build executable
add_executable(vod-server ${SOURCES})

# Link libraries
target_link_libraries(vod-server
    ${MHD_LIBRARIES}
    ${SQLITE3_LIBRARIES}
    ${CMAKE_THREAD_LIBS_INIT}
    m
)

if(GNUTLS_FOUND)
    target_link_libraries(vod-server ${GNUTLS_LIBRARIES})
endif()

# Link directories
target_link_directories(vod-server PRIVATE
    ${MHD_LIBRARY_DIRS}
    ${SQLITE3_LIBRARY_DIRS}
)

if(GNUTLS_FOUND)
    target_link_directories(vod-server PRIVATE ${GNUTLS_LIBRARY_DIRS})
endif()

# Install
install(TARGETS vod-server DESTINATION /usr/local/bin)
install(DIRECTORY www/ DESTINATION /usr/local/share/vod-server/www)
install(FILES config/vod-server.conf DESTINATION /etc/vod-server
        PERMISSIONS OWNER_READ OWNER_WRITE GROUP_READ)
install(FILES scripts/vod-server.service DESTINATION /etc/systemd/system)

# Print config summary
message(STATUS "=== VOD Server Build Configuration ===")
message(STATUS "Version:        ${VOD_SERVER_VERSION_MAJOR}.${VOD_SERVER_VERSION_MINOR}.${VOD_SERVER_VERSION_PATCH}")
message(STATUS "Build type:     ${CMAKE_BUILD_TYPE}")
message(STATUS "libmicrohttpd:  ${MHD_VERSION}")
message(STATUS "SQLite3:        ${SQLITE3_VERSION}")
message(STATUS "GnuTLS:         ${GNUTLS_FOUND} ${GNUTLS_VERSION}")
message(STATUS "======================================")
