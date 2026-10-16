"""Bounded deterministic English/Taglish phrase interpretation. No external services."""
import json
import re
import sys
import unicodedata
from pathlib import Path

RULES = json.loads(Path(__file__).with_name("concerns.json").read_text(encoding="utf-8"))
NEGATIONS = {"not", "no", "never", "without", "isnt", "arent", "dont", "hindi", "di", "walang", "wala"}
BOUNDARIES = re.compile(r"[.!?;,\n]|\b(?:but|pero|however|although|and|at|while|whereas|yet|kaso|ngunit)\b")


def interpret(text):
    if not isinstance(text, str) or len(text) > 500 or len(text.encode("utf-8")) > 2000:
        raise ValueError("Text limit")
    normalized = unicodedata.normalize("NFKC", text).casefold()
    normalized = re.sub(r"['’]", "", normalized)
    normalized = re.sub(r"[-_/]", " ", normalized)
    matches = []
    for clause in BOUNDARIES.split(normalized):
        tokens = re.findall(r"[^\W_]+", clause, re.UNICODE)
        candidates = []
        for rule in RULES["rules"]:
            for phrase in rule["phrases"]:
                words = phrase.split()
                for start in range(len(tokens) - len(words) + 1):
                    if tokens[start:start + len(words)] == words:
                        candidates.append((start, start + len(words), phrase, rule))
        # Longest phrase wins, so "dry scalp" cannot also become dry lengths.
        used = set()
        for start, end, phrase, rule in sorted(candidates, key=lambda x: (-(x[1] - x[0]), x[0], x[3]["id"])):
            if any(pos in used for pos in range(start, end)):
                continue
            used.update(range(start, end))
            before = tokens[max(0, start - 4):start]
            negated = any(word in NEGATIONS for word in before)
            # "not only dry" is an additive phrase rather than a negation.
            if len(before) >= 2 and before[-2:] == ["not", "only"]:
                negated = False
            kind, code, scope, rule_id = rule["type"], rule["code"], rule["scope"], rule["id"]
            if phrase in ("oily", "greasy", "mamantika", "dry", "tuyo"):
                subjects = [(abs(pos - start), pos, word) for pos, word in enumerate(tokens)
                            if word in {"scalp", "roots", "root", "anit", "hair", "ends", "lengths", "buhok"}]
                if subjects and min(subjects)[2] in {"scalp", "roots", "root", "anit"}:
                    kind, scope = "scalp", "scalp"
                    code = "dry_scalp" if phrase in ("dry", "tuyo") else "oily_scalp"
                    rule_id = "context." + code
            matches.append({"type": kind, "code": code, "scope": scope,
                            "rule": rule_id, "phrase": phrase, "negated": negated})
    warnings = []
    positive = {(m["type"], m["code"]) for m in matches if not m["negated"]}
    negative = {(m["type"], m["code"]) for m in matches if m["negated"]}
    conflicts = positive & negative
    for kind in ("scalp", "texture"):
        choices = {key for key in positive if key[0] == kind}
        if len(choices) > 1:
            conflicts |= choices
    if conflicts:
        warnings.append("Conflicting descriptions were left out. Use the structured choices to clarify.")
    tags = [{"type": kind, "code": code} for kind, code in sorted(positive - conflicts)]
    if not tags:
        warnings.append("No clear cosmetic hair characteristics were recognized. Your structured choices still apply.")
    return {"version": RULES["version"], "tags": tags, "matches": matches,
            "warnings": warnings, "status": "recognized" if tags else "unknown"}


if __name__ == "__main__":
    try:
        payload = sys.stdin.buffer.read(4097)
        if len(payload) > 4096:
            raise ValueError("Input limit")
        data = json.loads(payload.decode("utf-8"))
        result = interpret(data["text"])
        sys.stdout.write(json.dumps(result, ensure_ascii=True, separators=(",", ":")))
    except (ValueError, KeyError, TypeError, UnicodeError):
        sys.stderr.write("Invalid concern input.\n")
        sys.exit(1)
