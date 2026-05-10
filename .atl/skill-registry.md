# Skill Registry

**Orchestrator use only.** Read this registry once per session to resolve skill paths, then pass pre-resolved paths directly to each sub-agent's launch prompt. Sub-agents receive the path and load the skill directly — they do NOT read this registry.

## User Skills

| Trigger | Skill | Path |
|---------|-------|------|
| how do I do X, find a skill for X, is there a skill that can... | find-skills | file:///C:/Users/Javier/.agents/skills/find-skills/SKILL.md |
| update skills, skill registry, actualizar skills, update registry | skill-registry | file:///C:/Users/Javier/.config/opencode/skills/skill-registry/SKILL.md |
| sdd verify, verify implementation | sdd-verify | file:///C:/Users/Javier/.config/opencode/skills/sdd-verify/SKILL.md |
| sdd tasks, implementation tasks | sdd-tasks | file:///C:/Users/Javier/.config/opencode/skills/sdd-tasks/SKILL.md |
| sdd spec, write specs, specification | sdd-spec | file:///C:/Users/Javier/.config/opencode/skills/sdd-spec/SKILL.md |
| sdd propose, change proposal | sdd-propose | file:///C:/Users/Javier/.config/opencode/skills/sdd-propose/SKILL.md |
| sdd init, iniciar sdd, openspec init | sdd-init | file:///C:/Users/Javier/.config/opencode/skills/sdd-init/SKILL.md |
| sdd explore, think through, investigate | sdd-explore | file:///C:/Users/Javier/.config/opencode/skills/sdd-explore/SKILL.md |
| sdd design, technical design | sdd-design | file:///C:/Users/Javier/.config/opencode/skills/sdd-design/SKILL.md |
| sdd archive, archive change | sdd-archive | file:///C:/Users/Javier/.config/opencode/skills/sdd-archive/SKILL.md |
| sdd apply, implement change | sdd-apply | file:///C:/Users/Javier/.config/opencode/skills/sdd-apply/SKILL.md |

## Project Conventions

| File | Path | Notes |
|------|------|-------|
| - | - | No project conventions found |

Read the convention files listed above for project-specific patterns and rules. All referenced paths have been extracted — no need to read index files to discover more.
