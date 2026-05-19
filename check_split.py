with open(r'd:\Sherdor 2.0\resources\js\Pages\Admin\HR\Index.vue', 'r', encoding='utf-8') as f:
    text = f.read()

count = text.count("split('\\')")
print(f"Found {count} occurrences of split('\\')")
