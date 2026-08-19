import codecs

path = "resources/js/Pages/Admin/Finance/Index.vue"
with codecs.open(path, 'r', 'utf-8') as f:
    content = f.read()

content = content.replace("@input=\"{var} = unformatSpaces(\.target.value)\"", "@input=\"{var} = unformatSpaces(\.target.value)\"")

# Wait, the python script had f-string f':value="formatSpaces({var})" @input="{var} = unformatSpaces(\.target.value)"'
# Let's just fix it by replacing the bad strings directly.
content = content.replace("unformatSpaces(.target.value)", "unformatSpaces(.target.value)")

with codecs.open(path, 'w', 'utf-8') as f:
    f.write(content)

print("Fixed event.target.value")
