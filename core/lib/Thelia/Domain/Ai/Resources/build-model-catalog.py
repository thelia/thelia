#!/usr/bin/env python3
"""Build model-catalog.json from LiteLLM's model_prices_and_context_window.json (MIT).

Usage: python3 -I build-model-catalog.py [path/to/model_prices_and_context_window.json [commit-label]]
Without argument: downloads the file into a temporary directory first. With a local file, the optional label
(`<sha> <commit date>`) is written as the source commit, so a catalogue is rebuilt from the very commit it names.

Fine-tuned models (`ft:*`) and models whose deprecation date has passed are left out.
"""
import json, subprocess, sys, tempfile, urllib.request, datetime
from decimal import Decimal
from pathlib import Path

HERE = Path(__file__).resolve().parent
RAW = "https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.json"
API = "https://api.github.com/repos/BerriAI/litellm/commits?path=model_prices_and_context_window.json&per_page=1"
# our provider name -> litellm_provider key (None = absent from LiteLLM, never invented)
PROVIDERS = {"mistral": "mistral", "anthropic": "anthropic", "openai": "openai",
             "scaleway": "scaleway", "ovhcloud": "ovhcloud", "albert": None, "ollama": "ollama"}
MODES = {"chat", "completion"}
# What LiteLLM does not say: the `thinking` setting that keeps an Anthropic model from thinking before it answers,
# measured on the API on 2026-10-29 (fable-5, fable-5-1, opus-5-5, sonnet-5-5, haiku-5-5), the others from the table
# of the thinking documentation of Anthropic. "adaptive" marks a model that refuses "disabled" and has no off switch:
# the adapter asks it for the lowest effort. A model left out is sent "disabled", and the adapter learns from a refusal.
THINKING_OFF = {("anthropic", "claude-fable-5"): "adaptive", ("anthropic", "claude-fable-5-1"): "adaptive",
                ("anthropic", "claude-mythos-5"): "adaptive", ("anthropic", "claude-mythos-5-1"): "adaptive",
                ("anthropic", "claude-opus-5-5"): "adaptive", ("anthropic", "claude-sonnet-5-5"): "between_tools",
                ("anthropic", "claude-haiku-5-5"): "disabled", ("anthropic", "claude-opus-5"): "disabled",
                ("anthropic", "claude-sonnet-5"): "disabled"}


def money(per_token):
    if per_token is None:
        return None
    d = (Decimal(str(per_token)) * 1_000_000).normalize()
    s = format(d, "f")
    if "." not in s:
        s += ".00"
    elif len(s.split(".")[1]) < 2:
        s += "0"
    return s


def is_listed(model, deprecation_date, today):
    """False for a fine-tuned model (it belongs to one account) and for a model past its deprecation date."""
    return not model.startswith("ft:") and not (deprecation_date and deprecation_date < today)


def download(url, dest):
    try:
        with urllib.request.urlopen(url, timeout=20) as r:
            dest.write_bytes(r.read())
    except Exception:  # some networks stall urllib on raw.githubusercontent.com: fall back to curl
        subprocess.run(["curl", "-fsSL", "--max-time", "90", "-o", str(dest), url], check=True)


def main():
    commit = None
    if len(sys.argv) > 1:
        src = Path(sys.argv[1])
        commit = sys.argv[2] if len(sys.argv) > 2 else "local file " + src.name
    else:
        src = Path(tempfile.mkdtemp(prefix="litellm-prices-")) / "model_prices_and_context_window.json"
        download(RAW, src)
        try:
            with urllib.request.urlopen(API, timeout=30) as r:
                c = json.load(r)[0]
            commit = c["sha"] + " " + c["commit"]["committer"]["date"]
        except Exception:
            commit = "download " + datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds")
    data = json.loads(src.read_text(encoding="utf-8"))
    models, counts = [], {}
    today = datetime.date.today().isoformat()
    for name, provider_key in PROVIDERS.items():
        counts[name] = 0
        if provider_key is None:
            continue
        for key, v in data.items():
            if not isinstance(v, dict) or v.get("litellm_provider") != provider_key or v.get("mode") not in MODES:
                continue
            if "audio" in key or key.endswith("container"):
                continue  # audio-in/out chat variants and non-model entries
            model = key[len(provider_key) + 1:] if key.startswith(provider_key + "/") else key
            if not is_listed(model, v.get("deprecation_date"), today):
                continue
            thinking_off = THINKING_OFF.get((name, model))
            models.append({
                "provider": name, "model": model, "mode": v["mode"],
                "input_usd_per_million": money(v.get("input_cost_per_token")),
                "output_usd_per_million": money(v.get("output_cost_per_token")),
                "cache_read_usd_per_million": money(v.get("cache_read_input_token_cost")),
                "cache_write_usd_per_million": money(v.get("cache_creation_input_token_cost")),
                "context_window": v.get("max_input_tokens", v.get("max_tokens")),
                "max_output_tokens": v.get("max_output_tokens"),
                "capabilities": {"structured_output": v.get("supports_response_schema"),
                                 "tools": v.get("supports_function_calling"),
                                 "vision": v.get("supports_vision")},
                "deprecation_date": v.get("deprecation_date"),
                **({"thinking_off": thinking_off} if thinking_off else {}),
            })
            counts[name] += 1
    models.sort(key=lambda m: (m["provider"], m["model"]))
    out = {"source": RAW, "source_commit_or_date": commit, "license": "MIT",
           "attribution": "Copyright (c) 2023 Berri AI",
           "generated_at": datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds"),
           "models": models}
    (HERE / "model-catalog.json").write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(counts))


main()
