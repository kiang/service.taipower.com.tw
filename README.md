# Taipower Service Data

A PHP-based data extraction and mapping system for Taiwan Power Company (Taipower) service area information.

## Overview

This project fetches hierarchical administrative data from Taipower's public API and maps it to Taiwan's standard administrative codes (VILLCODE). The system extracts county, town, and village-level data with power outage statistics.

## Features

- **Data Fetching**: Retrieves county, town, and village data from Taipower API
- **Hierarchical Processing**: Handles nested administrative levels automatically
- **VILLCODE Mapping**: Maps Taipower data to Taiwan's standard village codes
- **CSV Export**: Generates structured CSV files for data analysis
- **Manual Mapping**: Includes manual corrections for unmatched records
- **Sorted Output**: Data sorted by GID and VILLCODE for consistency

## Data Structure

### Input Data (Taipower API)
- Counties with GID and FCID identifiers
- Towns within each county
- Villages (cunli) within each town
- Power outage counts (c0, c1) for each administrative unit

### Output Files

#### `docs/cunli.csv`
Contains raw Taipower data sorted by GID:
- `county_name`: County name
- `town_name`: Town name  
- `cunli_name`: Village name
- `c0`: Power outage count type 0
- `c1`: Power outage count type 1
- `fcid`: Taipower facility code
- `gid`: Taipower geographic identifier

#### `docs/taipower.csv`
Contains mapped data sorted by VILLCODE:
- `VILLCODE`: Taiwan standard village code
- `c0`: Power outage count type 0
- `c1`: Power outage count type 1

## Scripts

### `scripts/01_fetch.php`
Fetches data from Taipower API and generates cunli.csv:
- Retrieves county list from API
- Fetches town data for each county
- Processes village-level data for areas with outages
- Saves raw JSON responses to `raw/` directory
- Generates sorted CSV output

### `scripts/02_mapping.php`
Maps Taipower data to standard village codes:
- Loads Taiwan basecode data from TopJSON format
- Matches administrative names to get VILLCODE
- Includes manual mapping for 28 special cases
- Generates sorted taipower.csv output

## Usage

1. **Fetch Taipower Data**:
   ```bash
   php scripts/01_fetch.php
   ```

2. **Generate VILLCODE Mapping**:
   ```bash
   php scripts/02_mapping.php
   ```

## Data Sources

- **Taipower API**: `https://service.taipower.com.tw/psvs1/nj_psvs_attr/RangeInfo/`
- **Taiwan Basecode**: `/home/kiang/public_html/taiwan_basecode/cunli/topo/20240807.json`

## Dependencies

- PHP 7.4+ with cURL extension
- Access to Taipower public API
- Taiwan basecode TopJSON file

## Output Statistics

The mapping process typically achieves:
- High match rate (>95%) for automatic name matching
- Manual corrections for edge cases and naming variations
- Complete coverage of all Taipower service areas

## License

MIT License - see LICENSE file for details.

## Author

Finjon Kiang