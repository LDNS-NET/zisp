import csv

# Read the CSV file
input_file = r'c:\Users\LDNS NETWORKS\Documents\zimaradius\sessions_data.csv'
output_file = r'c:\Users\LDNS NETWORKS\Documents\zimaradius\sessions_data.csv'

rows = []
updated_count = 0

with open(input_file, 'r', encoding='utf-8') as f:
    reader = csv.DictReader(f)
    fieldnames = reader.fieldnames
    
    for row in reader:
        # Check if password is empty or just whitespace
        if not row.get('password') or row.get('password').strip() == '':
            row['password'] = '12345678'
            updated_count += 1
        rows.append(row)

# Write back to the same file
with open(output_file, 'w', newline='', encoding='utf-8') as f:
    writer = csv.DictWriter(f, fieldnames=fieldnames)
    writer.writeheader()
    writer.writerows(rows)

print(f'Successfully updated {updated_count} empty password fields to "12345678"')
print(f'Total rows processed: {len(rows)}')
