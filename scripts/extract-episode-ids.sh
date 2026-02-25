#!/bin/bash
#
# extract-episode-ids.sh
#
# @copyright 2026 Eric Peterson (eric@puzzlehead.org)
# @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
#
# Takes a CSV file with a URL column and extracts episode IDs from each URL's content.
# Adds a new column "episode_id" with the extracted ID.
#
# Usage: ./extract-episode-ids.sh input.csv output.csv [url_column_name]
#
# Arguments:
#   input.csv        - Input CSV file containing URLs
#   output.csv       - Output CSV file with episode_id column added
#   url_column_name  - (Optional) Name of the column containing URLs (default: "episode_url")
#

set -e

# Check arguments
if [ $# -lt 2 ]; then
    echo "Usage: $0 input.csv output.csv [url_column_name]"
    echo ""
    echo "Arguments:"
    echo "  input.csv        - Input CSV file containing URLs"
    echo "  output.csv       - Output CSV file with episode_id column added"
    echo "  url_column_name  - (Optional) Name of column containing URLs (default: episode_url)"
    exit 1
fi

INPUT_FILE="$1"
OUTPUT_FILE="$2"
URL_COLUMN="${3:-episode_url}"

# Check if input file exists
if [ ! -f "$INPUT_FILE" ]; then
    echo "Error: Input file '$INPUT_FILE' not found."
    exit 1
fi

# Check for required tools
if ! command -v curl &> /dev/null; then
    echo "Error: curl is required but not installed."
    exit 1
fi

# Read the header line and find the URL column index
HEADER=$(head -n 1 "$INPUT_FILE")
IFS=',' read -ra COLUMNS <<< "$HEADER"

URL_COL_INDEX=-1
for i in "${!COLUMNS[@]}"; do
    # Remove quotes and whitespace from column name
    COL_NAME=$(echo "${COLUMNS[$i]}" | tr -d '"' | tr -d "'" | xargs)
    if [ "$COL_NAME" = "$URL_COLUMN" ]; then
        URL_COL_INDEX=$i
        break
    fi
done

if [ $URL_COL_INDEX -eq -1 ]; then
    echo "Error: Column '$URL_COLUMN' not found in CSV header."
    echo "Available columns: $HEADER"
    exit 1
fi

echo "Found URL column '$URL_COLUMN' at index $URL_COL_INDEX"

# Function to extract episode ID from URL content
extract_episode_id() {
    local url="$1"
    
    # Skip empty URLs
    if [ -z "$url" ] || [ "$url" = '""' ]; then
        echo ""
        return
    fi
    
    # Remove surrounding quotes if present
    url=$(echo "$url" | tr -d '"' | tr -d "'")
    
    # Fetch the URL content and extract the episode ID
    # Looking for pattern: /embed/episode/id/XXXXXXXX/
    local content
    content=$(curl -s -L --max-time 30 "$url" 2>/dev/null || echo "")
    
    if [ -n "$content" ]; then
        # Extract episode ID using grep and sed
        local episode_id
        episode_id=$(echo "$content" | grep -oP '//html5-player\.libsyn\.com/embed/episode/id/\K[0-9]+' | head -n 1)
        
        if [ -z "$episode_id" ]; then
            # Try alternative pattern without the full domain
            episode_id=$(echo "$content" | grep -oP '/embed/episode/id/\K[0-9]+' | head -n 1)
        fi
        
        echo "$episode_id"
    else
        echo ""
    fi
}

# Create output file with new header
echo "${HEADER},episode_id" > "$OUTPUT_FILE"

# Process each data row
LINE_NUM=0
TOTAL_LINES=$(wc -l < "$INPUT_FILE")
TOTAL_LINES=$((TOTAL_LINES - 1))  # Exclude header

echo "Processing $TOTAL_LINES rows..."

# Skip header and process data rows
tail -n +2 "$INPUT_FILE" | while IFS= read -r line || [ -n "$line" ]; do
    LINE_NUM=$((LINE_NUM + 1))
    
    # Parse the CSV line to extract the URL
    # This handles basic CSV with quoted fields containing commas
    # For complex CSVs, consider using a proper CSV parser
    
    # Simple approach: split by comma (works if URL doesn't contain commas)
    IFS=',' read -ra FIELDS <<< "$line"
    
    URL="${FIELDS[$URL_COL_INDEX]}"
    
    echo -n "[$LINE_NUM/$TOTAL_LINES] Fetching: $URL ... "
    
    EPISODE_ID=$(extract_episode_id "$URL")
    
    if [ -n "$EPISODE_ID" ]; then
        echo "Found ID: $EPISODE_ID"
    else
        echo "No ID found"
    fi
    
    # Append the episode ID to the line and write to output
    echo "${line},${EPISODE_ID}" >> "$OUTPUT_FILE"
    
    # Small delay to be nice to the server
    sleep 0.5
done

echo ""
echo "Done! Output written to: $OUTPUT_FILE"
