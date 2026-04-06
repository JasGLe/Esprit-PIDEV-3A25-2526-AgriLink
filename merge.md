I need to merge new commits from my branch `features/module6` into the `dev` branch
to add the marketplace feature, without breaking any existing changes on `dev`.

Before doing anything, please ask me the following questions one by one and wait
for my answers before proceeding:

1. What is the current state of my working directory?
   (Do I have uncommitted changes on `features/module6`?)

2. Are there any commits on `dev` that are NOT yet in `features/module6`?
   (i.e., has `dev` moved forward since I branched off?)

3. Should we use **merge** or **rebase** strategy?
    - Merge: preserves full history, creates a merge commit
    - Rebase: replays my commits on top of dev, cleaner linear history

4. Are there any known files/areas likely to have conflicts between
   `features/module6` and `dev`?

5. Should we squash my marketplace commits into one clean commit before
   integrating, or keep each commit separate?

Once I answer all questions, then:

- Show me the exact Git commands you plan to run, step by step
- Explain what each command does before running it
- Do NOT execute anything until I give explicit approval for each step
- If any conflicts arise, pause and guide me through resolving them manually
- After a successful merge, show me a `git log --oneline --graph` summary
