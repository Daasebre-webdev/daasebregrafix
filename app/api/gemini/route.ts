
// app/api/gemini/route.ts
import { GoogleGenAI } from "@google/genai";

export async function POST(request: Request) {
  const formData = await request.json();


  try {
    const prompt = `
Generate 5 realistic, creative, and educational project ideas based on:
- Field of Study: ${formData.field}
- Skills: ${formData.skills}
- Interests: ${formData.interests}
- Complexity Level: ${formData.complexity}
- Preferred Technologies: ${formData.technologies}

Respond **only** with a raw JSON array.
Do **not** include any code fences (like \`\`\` or \`\`\`json), titles, or extra text.
Just return valid, plain JSON — no Markdown, no comments.

**Requirements for learningObjectives:**
- Must be an array of strings (e.g., ["Objective 1", "Objective 2"]).
- Each item should be a concise, actionable goal (5-10 words max).
- Do not use numbered lists, bullet points, or plain text paragraphs.
Format:
[
  {
    "id": "unique-id",
    "title": "...",
    "description": "...",
    "category": "...",
    "details": {
    "fullDescription: "...",
     "difficulty": "...",
          "duration": "...",
          "learningObjectives": [
            "..."]
    }
  },
  ...
]
`


  
    const ai = new GoogleGenAI({apiKey : "AIzaSyBkVDKlrqpdIg2VTvmme8Ac0gvDiYZG5p4"});
    
    const model = await ai.models.generateContent({ model: "gemini-2.5-flash", contents: prompt });
    const response = await model;
    const text = response.text
    console.log({response, candidates:response.text}, 'ahhhhh');
    return Response.json({text})
  } catch (error: any) {
    console.error("Gemini error:", error);
    return Response.json({ error: error?.message || "Failed to generate response" }, { status: 500 });
  }
}





// import { GoogleGenAI } from "@google/genai";

// export async function POST(request: Request) {
//   const { message } = await request.json();

//   try {
//     // const genAI = new GoogleGenAI(process.env.GEMINI_API_KEY!);
//     const ai = new GoogleGenAI({apiKey : "AIzaSyBkVDKlrqpdIg2VTvmme8Ac0gvDiYZG5p4"});

//     const model = await ai.models.generateContent({ model: "gemini-2.5-flash", contents: message });
//     const response = await model;
//     // const text = await response.text; 
//     console.log({response, candidates:response.text, message});

//     return Response.json({ text:response.text});

//   } catch (error) {
//     console.error("Gemini error:", error); 
//     return Response.json({ error: "Failed to get response" }, { status: 500 });
//   }
// }
