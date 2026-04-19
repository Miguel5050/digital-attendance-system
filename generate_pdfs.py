import urllib.request
import json
import io
from PIL import Image, ImageDraw, ImageFont

usecase_uml = """@startuml
skinparam handwritten false
skinparam backgroundColor white
skinparam BoxPadding 10
skinparam ParticipantPadding 20
skinparam ClassBackgroundColor white

left to right direction
actor "Student" as student
actor "Lecturer" as lecturer
actor "Admin" as admin

rectangle "Digital Attendance System" {
  usecase "Authenticate (Login/Logout)" as UC_Auth
  usecase "Manage Users & Courses" as UC_Manage
  usecase "Upload CSV (Enrollments)" as UC_CSV
  usecase "Create Attendance Session" as UC_CreateSession
  usecase "Monitor Real-time Attendance" as UC_Monitor
  usecase "Submit Attendance (GPS validation)" as UC_Submit
  usecase "View Past Attendance" as UC_ViewStudent
  usecase "View Course Attendance Reports" as UC_ViewCourse
  usecase "Send/Receive Messages" as UC_Message
  usecase "Submit Attendance Claim" as UC_Claim
  usecase "Review Attendance Claim" as UC_ReviewClaim
}

student --> UC_Auth
student --> UC_Submit
student --> UC_ViewStudent
student --> UC_Message
student --> UC_Claim

lecturer --> UC_Auth
lecturer --> UC_CreateSession
lecturer --> UC_Monitor
lecturer --> UC_ViewCourse
lecturer --> UC_Message
lecturer --> UC_ReviewClaim

admin --> UC_Auth
admin --> UC_Manage
admin --> UC_CSV
admin --> UC_ViewCourse
admin --> UC_ReviewClaim
@enduml
"""

sequence_uml = """@startuml
skinparam handwritten false
skinparam backgroundColor white

actor Student as student
participant "Browser (GPS)" as browser
participant "API endpoints" as api
database "MySQL Db" as db

student -> browser : Clicks "Mark Attendance"
browser -> browser : Request Location (Lat, Lng)
browser -> api : POST /api/mark_attendance.php (Session Code, GPS)
api -> db : Query Session (code, is_active)
db --> api : Session Details (Lecturer GPS, duration)
alt Session Not Active or Invalid
    api --> browser : Error "Invalid or Expired Session"
else Valid Session
    api -> api : Calculate Distance (Student GPS vs Lecturer GPS)
    alt Distance > 50 meters
        api --> browser : Error "Out of Range"
        api -> db : Log Failed Attempt
    else Distance <= 50 meters
        api -> db : Insert Attendance (student_id, present)
        db --> api : Success
        api --> browser : Success "Attendance Marked"
        browser --> student : Show Success Notification
    end
end
@enduml
"""

activity_uml = """@startuml
skinparam handwritten false
skinparam backgroundColor white

|Lecturer|
start
:Login to Portal;
:Select Course Group;
:Click "Create Session";
|System|
:Generate 6-digit Code;
:Save Session (Code, Lecturer GPS, Expire Time);
:Display Code & Start Dashboard Polling;
|Lecturer|
:Share Code with Students;

|Student|
:Login to Portal;
:Enter 6-digit Code;
:Allow GPS Location;
:Submit Location;

|System|
:Validate Code & Expiry;
:Calculate Distance (Student GPS vs Lecturer GPS);
if (Distance <= Threshold?) then (Yes)
  :Record "Present";
  :Update Lecturer Dashboard (via Polling);
  |Student|
  :Show Success Message;
else (No)
  |System|
  :Log Failed Attempt;
  |Student|
  :Show "Out of Range" Error;
end if
stop
@enduml
"""

class_uml = """@startuml
skinparam handwritten false
skinparam backgroundColor white

class User {
  +id: int
  +student_id: String
  +name: String
  +email: String
  +role: RoleEnum
}

class Course {
  +id: int
  +code: String
  +name: String
}

class Group {
  +id: int
  +group_code: String
}

class CourseGroup {
  +id: int
  +room_lat: decimal
  +room_lng: decimal
  +total_sessions: int
}

class Enrollment {
  +id: int
}

class AttendanceSession {
  +id: int
  +code: String
  +duration_minutes: int
  +status: StatusEnum
  +lecturer_lat: decimal
  +lecturer_lng: decimal
}

class AttendanceRecord {
  +id: int
  +status: AttendanceEnum
  +location_lat: decimal
  +location_lng: decimal
}

class Communication {
  +id: int
  +message: Text
  +timestamp: DateTime
}

User "1" -- "*" CourseGroup : Teaches
User "1" -- "*" Enrollment : Enrolled in
Course "1" -- "*" CourseGroup
Group "1" -- "*" CourseGroup
CourseGroup "1" -- "*" Enrollment
CourseGroup "1" -- "*" AttendanceSession
AttendanceSession "1" -- "*" AttendanceRecord
User "1" -- "*" AttendanceRecord : Submits
User "1" -- "*" Communication : Sends/Receives
CourseGroup "1" -- "*" Communication
@enduml
"""

diagrams = [
    ("1. Use Case Diagram", usecase_uml),
    ("2. Sequence Diagram", sequence_uml),
    ("3. Activity Diagram (Swimlane)", activity_uml),
    ("4. Class Diagram", class_uml)
]

def render_plantuml_kroki(source, format="png"):
    payload = {"diagram_source": source}
    data = json.dumps(payload).encode('utf-8')
    req = urllib.request.Request(
        f"https://kroki.io/plantuml/{format}",
        data=data,
        headers={
            'Content-Type': 'application/json',
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        }
    )
    with urllib.request.urlopen(req) as resp:
        return resp.read()

images = []

for title, uml in diagrams:
    print(f"Rendering: {title}")
    png_data = render_plantuml_kroki(uml)
    img = Image.open(io.BytesIO(png_data)).convert("RGB")

    new_width = max(img.width + 100, 800)
    new_height = img.height + 250

    canvas = Image.new("RGB", (new_width, new_height), "white")
    draw = ImageDraw.Draw(canvas)
    
    try:
        font = ImageFont.truetype("arial.ttf", 36)
    except:
        font = ImageFont.load_default()

    draw.text((50, 50), title, fill="black", font=font)
    canvas.paste(img, (50, 150))
    images.append(canvas)

if images:
    images[0].save(
        "Software_Modeling_Design.pdf",
        save_all=True,
        append_images=images[1:],
        resolution=100.0
    )
    print("PDF saved as Software_Modeling_Design.pdf")
