# UI / Styling Standards

## 1. ใช้มาตรฐานสากลในการออกแบบ Style

การออกแบบ UI และ CSS ต้องยึดหลักการออกแบบ Web ที่เป็นมาตรฐานสากล โดยเน้น:

* Consistency
* Usability
* Accessibility
* Readability
* Visual hierarchy
* Responsive design
* Maintainability
* Predictable interaction

ห้ามออกแบบตามความรู้สึกเพียงอย่างเดียว

ทุก element ที่มีหน้าที่เหมือนกันควรมีรูปแบบที่เหมือนกันหรือใกล้เคียงกัน

ตัวอย่าง:

```text
ปุ่ม Submit ทุกหน้า
→ ใช้รูปแบบเดียวกัน

ปุ่ม Cancel ทุกหน้า
→ ใช้รูปแบบเดียวกัน

Status Badge ประเภทเดียวกัน
→ ใช้รูปแบบเดียวกัน

Form Input ประเภทเดียวกัน
→ ใช้รูปแบบเดียวกัน
```

---

# 2. Pixel-Perfect Mindset

ให้ Agent คิดเสมือนว่า:

> **คนที่ตรวจงานเป็นคนที่มี OCD และต้องการความเป๊ะสูงมาก**

ดังนั้น UI ต้องมีความเป็นระเบียบ สม่ำเสมอ และไม่มีรายละเอียดเล็ก ๆ ที่ดูขัดกัน

ไม่ได้หมายความว่าต้องทำให้ซับซ้อน แต่หมายถึงต้อง **ใส่ใจรายละเอียดทุกจุด**

---

## 2.1 Spacing ต้องสม่ำเสมอ

ต้องตรวจสอบระยะห่างระหว่าง:

* Section
* Card
* Form field
* Label
* Input
* Button
* Icon
* Text
* Table
* Modal

ไม่ควรกำหนด spacing แบบสุ่ม เช่น:

```text
12px
17px
23px
31px
```

โดยไม่มีเหตุผล

ควรใช้ spacing scale ที่เป็นระบบ เช่น:

```text
4px
8px
12px
16px
24px
32px
48px
64px
```

หาก project มี design token หรือ spacing system อยู่แล้ว ให้ใช้ของเดิมก่อน

---

# 2.2 Alignment ต้องเป๊ะ

ตรวจสอบ alignment ของ:

* Text
* Input
* Button
* Icon
* Table
* Card
* Section
* Header
* Footer

องค์ประกอบที่อยู่ในกลุ่มเดียวกันควร align กันอย่างชัดเจน

ตัวอย่าง:

```text
Label
Input
Input
Input
```

ควรมีแนวเดียวกัน

ห้ามเกิดลักษณะ:

```text
Label       Input
Long Label      Input
Label     Input
```

โดยไม่มีเหตุผลด้าน layout

---

# 2.3 Typography ต้องเป็นระบบ

ต้องใช้ Typography อย่างสม่ำเสมอ

ควรกำหนด hierarchy เช่น:

```text
Page Title
    ↓
Section Title
    ↓
Subsection
    ↓
Body
    ↓
Caption / Helper text
```

ต้องตรวจสอบ:

* Font family
* Font size
* Font weight
* Line height
* Letter spacing
* Text color
* Heading hierarchy

ไม่ควรใช้ font size แบบสุ่มในแต่ละ element

หาก project มี typography system อยู่แล้ว ต้องใช้ระบบเดิม

---

# 2.4 Color ต้องมีเหตุผล

สีต้องถูกใช้ตามหน้าที่ ไม่ใช่ใช้เพื่อความสวยงามเพียงอย่างเดียว

ตัวอย่าง:

```text
Primary
→ Action หลัก

Secondary
→ Action รอง

Success
→ สำเร็จ / อนุมัติ

Warning
→ ต้องระวัง / รอดำเนินการ

Danger
→ Error / ไม่อนุมัติ / ลบ

Neutral
→ ข้อมูลทั่วไป
```

ต้องรักษาความสม่ำเสมอของสีทั่วทั้งระบบ

ห้ามสร้างสีใหม่โดยไม่ตรวจสอบว่ามีสีที่ project ใช้อยู่แล้วหรือไม่

---

# 2.5 Border / Radius / Shadow ต้องสม่ำเสมอ

องค์ประกอบประเภทเดียวกันควรใช้:

* Border width
* Border color
* Border radius
* Shadow

ในรูปแบบเดียวกัน

ตัวอย่าง:

```text
Input
Select
Textarea
File Upload
```

ควรมี visual language เดียวกัน

ไม่ควรมี:

```text
Input     border-radius: 8px
Select    border-radius: 4px
Textarea  border-radius: 12px
```

โดยไม่มีเหตุผล

---

# 2.6 Button ต้องมี Hierarchy

Button ต้องสื่อความสำคัญของ Action

ตัวอย่าง:

```text
Primary
[ส่งคำขอ]

Secondary
[บันทึกร่าง]

Danger
[ไม่อนุมัติ]

Ghost / Tertiary
[ยกเลิก]
```

ไม่ควรทำให้ทุกปุ่มมี visual weight เท่ากันหมด

Action ที่สำคัญที่สุดของแต่ละหน้าควรโดดเด่นที่สุด

---

# 2.7 Form ต้องละเอียดและเป็นระบบ

Form ต้องตรวจสอบ:

* Label อยู่ตำแหน่งเดียวกัน
* Input height สม่ำเสมอ
* ระยะห่างระหว่าง field สม่ำเสมอ
* Required indicator มีรูปแบบเดียวกัน
* Error message มีตำแหน่งที่แน่นอน
* Helper text มีรูปแบบเดียวกัน
* Disabled state ชัดเจน
* Focus state ชัดเจน

ตัวอย่างโครงสร้าง:

```text
Label
Input
Helper text / Error message
```

ต้องไม่สลับรูปแบบไปมาในแต่ละ field

---

# 2.8 Responsive Design

ทุกหน้าต้องพิจารณาการแสดงผลอย่างน้อย:

```text
Desktop
Tablet
Mobile
```

ต้องไม่ออกแบบโดยคิดเฉพาะ Desktop

ตรวจสอบ:

* Layout
* Width
* Spacing
* Table
* Form
* Button
* Navigation
* Modal
* File upload

บนหน้าจอขนาดเล็กด้วย

---

# 2.9 Accessibility

ต้องคำนึงถึง accessibility ตามมาตรฐาน Web ที่เหมาะสม

อย่างน้อย:

* Form ต้องมี label
* Button ต้องบอกหน้าที่ชัดเจน
* Interactive element ต้องสามารถใช้งานด้วย keyboard ได้
* Focus state ต้องมองเห็นได้
* สีไม่ควรเป็นวิธีเดียวในการสื่อความหมาย
* Contrast ต้องอ่านได้ง่าย
* Error message ต้องเข้าใจง่าย

ห้ามทำ UI ที่สวยแต่ใช้งานจริงยาก

---

# 3. Visual Consistency Rule

ก่อนสร้าง UI ใหม่ ให้ตรวจสอบ UI ที่มีอยู่ใน project ก่อน

ต้องถามตัวเองว่า:

```text
มี Component เดิมหรือไม่?
มี Style เดิมหรือไม่?
มี Design Token เดิมหรือไม่?
มี Pattern เดิมหรือไม่?
```

ถ้ามี:

> **ใช้ของเดิมก่อนเสมอ**

ห้ามสร้าง visual style ใหม่เพียงเพราะต้องการให้หน้าใหม่ดูแตกต่าง

---

# 4. Detail Inspection

ก่อนส่งงาน Agent ต้องตรวจสอบรายละเอียดเล็ก ๆ อย่างน้อย:

* [ ] Spacing เท่ากัน
* [ ] Alignment ถูกต้อง
* [ ] Font hierarchy ถูกต้อง
* [ ] Button ขนาดสม่ำเสมอ
* [ ] Input ขนาดสม่ำเสมอ
* [ ] Border radius สม่ำเสมอ
* [ ] สีสม่ำเสมอ
* [ ] Icon ขนาดเหมาะสม
* [ ] Icon alignment ถูกต้อง
* [ ] Text ไม่ล้น
* [ ] ไม่มี element เบียดกัน
* [ ] ไม่มี whitespace ที่ผิดปกติ
* [ ] ไม่มี CSS ที่ซ้ำโดยไม่จำเป็น
* [ ] Responsive layout ทำงานได้
* [ ] Hover state เหมาะสม
* [ ] Focus state เหมาะสม
* [ ] Disabled state เหมาะสม
* [ ] Loading state เหมาะสม
* [ ] Error state เหมาะสม
* [ ] Empty state เหมาะสม

---

# 5. Do Not "Just Make It Pretty"

ห้ามตีความคำว่า "ตกแต่ง" ว่า:

> เพิ่มสี เพิ่ม animation เพิ่ม shadow หรือเพิ่ม element ให้เยอะขึ้น

เป้าหมายคือ:

```text
Clean
Consistent
Precise
Readable
Accessible
Responsive
Professional
```

ความสวยต้องเกิดจาก **ความเป็นระบบและความสม่ำเสมอ** ไม่ใช่การตกแต่งที่มากเกินความจำเป็น

---

# 6. Final OCD-Level Review

ก่อนจบงาน ให้ตรวจ UI อีกรอบเสมือนเป็นผู้ตรวจงานที่ละเอียดมาก

ให้ตรวจจาก:

```text
ภาพรวม
  ↓
Layout
  ↓
Spacing
  ↓
Alignment
  ↓
Typography
  ↓
Color
  ↓
Component consistency
  ↓
Interaction
  ↓
Responsive
  ↓
Accessibility
  ↓
รายละเอียดระดับ Pixel
```

หากมีจุดใดที่ดู:

* เบี้ยว
* ไม่เท่ากัน
* ไม่สม่ำเสมอ
* ขนาดไม่สัมพันธ์กัน
* ระยะห่างไม่สมเหตุสมผล
* สีไม่เข้าระบบ
* Component หน้าตาไม่เหมือนส่วนอื่น
* Alignment ไม่ตรง
* มีรายละเอียดที่ดูเหมือน "ทำแบบรีบ ๆ"

ให้แก้ไขก่อนส่งงาน **แต่ต้องอยู่ภายใน Scope ของคำสั่ง User**

> **ความเป๊ะต้องไม่กลายเป็นข้ออ้างในการแก้สิ่งที่ User ไม่ได้สั่ง**
