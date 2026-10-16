"""Run with python -I -B tests/concerns.py. No database or network access."""
import importlib.util
import json
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location("concerns", Path(__file__).parents[1] / "nlp" / "interpret.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class Concerns(unittest.TestCase):
    def tags(self, text):
        return {(t["type"], t["code"]) for t in module.interpret(text)["tags"]}

    def test_required_examples(self):
        cases = [
            ("My scalp is oily but my ends are dry.", {("scalp", "oily_scalp"), ("concern", "dryness")}),
            ("Madaling mag-tangle yung buhok ko.", {("concern", "tangling")}),
            ("Frizzy and wavy hair.", {("concern", "frizz"), ("texture", "wavy")}),
            ("My scalp is not oily.", set()),
            ("Hindi naman dry yung hair ko.", set()),
            ("My hair was recently colored.", {("treatment", "colored")}),
            ("I have dry ends but oily roots.", {("scalp", "oily_scalp"), ("concern", "dryness")}),
            ("", set()), ("I like music.", set()),
        ]
        for text, expected in cases:
            with self.subTest(text=text):
                self.assertEqual(self.tags(text), expected)

    def test_negation_and_boundaries(self):
        for text in ["not dry", "no frizz", "without tangling", "hindi buhaghag", "walang tangles",
                     "isn't dry", "isn’t oily", "di naman tuyo"]:
            with self.subTest(text=text):
                self.assertEqual(self.tags(text), set())
        self.assertEqual(self.tags("not oily, but dry ends"), {("concern", "dryness")})
        self.assertEqual(self.tags("not only dry but frizzy"), {("concern", "dryness"), ("concern", "frizz")})
        self.assertEqual(self.tags("oily scalp is not dry"), {("scalp", "oily_scalp")})

    def test_overlap_scope_and_conflicts(self):
        result = module.interpret("dry scalp")
        self.assertEqual(self.tags("dry scalp"), {("scalp", "dry_scalp")})
        self.assertEqual(len(result["matches"]), 1)
        self.assertEqual(self.tags("oily scalp and dry scalp"), set())
        self.assertEqual(self.tags("dry hair but not dry hair"), set())
        self.assertEqual(self.tags("wavy and curly"), set())
        self.assertTrue(module.interpret("dry hair but not dry hair")["warnings"])
        self.assertEqual(self.tags("dry hair dry hair"), {("concern", "dryness")})
        self.assertEqual(self.tags("My scalp is oily while my ends feel dry."),
                         {("scalp", "oily_scalp"), ("concern", "dryness")})
        self.assertEqual(self.tags("My scalp is oily; my hair feels dry."),
                         {("scalp", "oily_scalp"), ("concern", "dryness")})
        self.assertEqual(self.tags("not oily yet dry ends"), {("concern", "dryness")})

    def test_unicode_case_punctuation(self):
        self.assertEqual(self.tags("  FRIZZY!! Wavy hair. "), {("concern", "frizz"), ("texture", "wavy")})
        self.assertEqual(self.tags("ｄｒｙ ends"), {("concern", "dryness")})
        self.assertEqual(self.tags("buhol-buhol"), {("concern", "tangling")})
        self.assertEqual(self.tags("superdry"), set())
        self.assertEqual(self.tags("hair loss infection eczema"), set())

    def test_explanations_and_determinism(self):
        text = "frizzy wavy hair"
        first = module.interpret(text)
        self.assertEqual(first, module.interpret(text))
        self.assertTrue(all(m["rule"] and m["phrase"] and m["scope"] for m in first["matches"]))
        self.assertEqual(first["version"], "1")
        self.assertEqual(module.interpret("unrecognized")["status"], "unknown")
        self.assertLess(len(json.dumps(module.interpret("dry " * 125))), 32768)

    def test_bounds(self):
        for invalid in ["a" * 501, "😀" * 501, None, 3, []]:
            with self.subTest(kind=type(invalid).__name__):
                with self.assertRaises(ValueError):
                    module.interpret(invalid)


if __name__ == "__main__":
    unittest.main(verbosity=2)
