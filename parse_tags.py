import re

with open('resources/js/Pages/Admin/Productivity/ProjectIndex.vue', 'r') as f:
    content = f.read()

# very simply count open tags vs close tags, ignoring self-closing tags
tags = re.findall(r'<(/?)([a-zA-Z0-9\-]+)[^>]*>', content)
stack = []
for tag in tags:
    is_close, name = tag
    # skip self-closing <img /> <input /> <hr /> <br /> and vue component self-closing like <ChevronUpIcon />
    if name in ['img', 'input', 'hr', 'br', 'Head']:
        continue
    # we can't reliably know which are self-closing if they end in /> just by regex easily without full match,
    # let's write a better parser using HTMLParser
    
