# Agent skill

`kadence-blocks/SKILL.md` bevat de werkwijzen en valkuilen die niet in een
toolschema passen — hoe de conventies per blok verschillen, waar tekst en
zichtbaarheid staan, en welke aanroep bij welke vraag hoort.

De serverbeschrijving draagt de conventies al en wordt automatisch geladen. Deze
skill is het niveau daarboven: hij beschrijft *werkwijzen*, en is bedoeld voor
wie de plugin op meerdere sites of met meerdere mensen gebruikt.

## Installeren

Kopieer de map `kadence-blocks` naar de skills-map van je agent:

    ~/.claude/skills/kadence-blocks/        (gebruikersbreed)
    <project>/.claude/skills/kadence-blocks/ (per project)

Claude laadt hem daarna zelf op grond van de `description` in de frontmatter.
