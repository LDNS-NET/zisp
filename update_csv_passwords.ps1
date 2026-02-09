$csvPath = "c:\Users\LDNS NETWORKS\Documents\zimaradius\sessions_data.csv"

# Read CSV file
$csv = Import-Csv -Path $csvPath

# Track updates
$updatedCount = 0

# Update empty password fields
foreach ($row in $csv) {
    if ([string]::IsNullOrWhiteSpace($row.password)) {
        $row.password = "12345678"
        $updatedCount++
    }
}

# Write back to file
$csv | Export-Csv -Path $csvPath -NoTypeInformation -Force

Write-Output "Successfully updated $updatedCount empty password fields to '12345678'"
Write-Output "Total rows processed: $($csv.Count)"
