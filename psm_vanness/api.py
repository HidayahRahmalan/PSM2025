from fastapi import FastAPI
import pandas as pd

app = FastAPI()

@app.post("/resolve_duplicates")
async def resolve_duplicates(data: dict):
    df = pd.DataFrame(data.get("duplicates", {}))
    if df.empty:
        return {"error": "No data received"}

    resolutions = [{"row": i, "action": "review"} for i in range(len(df))]
    return {"resolved": resolutions}

# Run the server
if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
