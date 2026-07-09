with open('resources/js/Pages/Admin/Partners/Assets.vue', 'r', encoding='utf-8') as f:
    content = f.read()

content = content.replace('class="bg-white text-slate-800"', 'style="background-color: #ffffff; color: #1e293b;"')

with open('resources/js/Pages/Admin/Partners/Assets.vue', 'w', encoding='utf-8') as f:
    f.write(content)
